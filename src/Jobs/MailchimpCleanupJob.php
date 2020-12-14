<?php

namespace NSWDPC\Chimple\Jobs;

use NSWDPC\Chimple\Models\MailchimpSubscriber;
use SilverStripe\Core\Config\Configurable;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use DateTime;
use Exception;

/**
 * Run through Mailchimp batches and subscribers to verify whether they are subscribed or not
 *
 * @author James
 */
class MailchimpCleanupJob extends AbstractQueuedJob implements QueuedJob
{
    use Configurable;

    private static $run_in_minutes = 30;

    public function __construct($minutes_ago = 30, $limit = 0, $report_only = 0)
    {
        $this->report_only = $report_only;
        $this->minutes_ago = $minutes_ago;
        $this->limit = $limit;
    }

    public function getTitle()
    {
        return sprintf(
            _t(
                __CLASS__ . '.TITLE',
                "Mailchimp cleanup job - minutes=%s limit=%s report_only=%s"
            ),
            $this->minutes_ago,
            $this->limit,
            $this->report_only
        );
    }

    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    public function setup()
    {
        $this->totalSteps = 1;
    }

    public function process()
    {
        $this->processSubscriptions();
        $this->isComplete = true;
        return;
    }

    private function processSubscriptions()
    {
        $prune_datetime = new DateTime();
        $minutes = $this->minutes_ago;
        $prune_datetime->modify("-{$minutes} minutes");

        $this->currentStep = 0;
        $success_deletes = 0;
        $failed_deletes = 0;

        // handle successful subscriptions
        $successful = MailchimpSubscriber::get()
                        ->filter([
                            'Status' => MailchimpSubscriber::CHIMPLE_STATUS_SUCCESS,
                            'Created:LessThan' => $prune_datetime->format('Y-m-d H:i:s')
                        ])
                        ->sort('Created ASC');//get the oldest first
        if ($this->limit > 0) {
            $successful = $successful->limit($this->limit);
        }

        if ($this->report_only) {
            $this->addMessage("Would delete {$successful->count()} subscribers with status " . MailchimpSubscriber::CHIMPLE_STATUS_SUCCESS);
        } else {
            foreach ($successful as $subscriber) {
                // remove this subscriber from the table
                $subscriber->delete();
                $success_deletes++;
            }
            $this->addMessage("Deleted {$success_deletes} subscribers with status " . MailchimpSubscriber::CHIMPLE_STATUS_SUCCESS);
        }


        $this->currentStep = $this->totalSteps = $success_deletes;

        // remove stale failed subscriptions older than 7 days
        $fail_datetime = new DateTime();
        $fail_datetime->modify('-7 days');
        $failed = MailchimpSubscriber::get()->filter([
                'Status' => MailchimpSubscriber::CHIMPLE_STATUS_FAIL,
                'Created:LessThan' => $fail_datetime->format('Y-m-d H:i:s')
        ]);

        if ($this->report_only) {
            $this->addMessage("Would delete {$failed->count()} subscribers with status " . MailchimpSubscriber::CHIMPLE_STATUS_FAIL);
        } else {
            foreach ($failed as $failed_subscriber) {
                // remove this subscriber from the table
                $failed_subscriber->delete();
                $failed_deletes++;
            }
            $this->addMessage("Deleted {$failed_deletes} subscribers with status " . MailchimpSubscriber::CHIMPLE_STATUS_FAIL);
        }


        $this->currentStep = $this->totalSteps = ($success_deletes + $failed_deletes);

        return true;
    }

    /**
     * Get next configured run time
     */
    private function getNextRunMinutes()
    {
        $minutes  = (int)$this->config()->get('run_in_minutes');
        if ($minutes <= 2) {
            // min every 2 minutes
            $minutes = 2;
        }
        return $minutes;
    }

    public function afterComplete()
    {
        $run_datetime = new DateTime();
        $minutes = $this->getNextRunMinutes();
        $run_datetime->modify("+{$minutes} minutes");
        // create a new job at the configured time
        singleton(QueuedJobService::class)->queueJob(new MailchimpCleanupJob($this->minutes_ago, $this->limit, $this->report_only), $run_datetime->format('Y-m-d H:i:s'));
    }
}
