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

    private static int $run_in_minutes = 30;

    public function __construct($minutes_ago = 30, $limit = 0, $report_only = 0)
    {
        $this->report_only = $report_only;
        $this->minutes_ago = $minutes_ago;
        $this->limit = $limit;
    }

    #[\Override]
    public function getTitle()
    {
        return sprintf(
            _t(
                self::class . '.TITLE',
                "Mailchimp cleanup job - minutes=%s limit=%s report_only=%s"
            ),
            $this->minutes_ago,
            $this->limit,
            $this->report_only
        );
    }

    #[\Override]
    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    #[\Override]
    public function setup(): void
    {
        $this->totalSteps = 1;
    }

    #[\Override]
    public function process(): void
    {
        $this->processSubscriptions();
        $this->isComplete = true;
    }

    private function processSubscriptions(): bool
    {
        $prune_datetime = new DateTime();
        $minutes = $this->minutes_ago;
        $prune_datetime->modify(sprintf('-%s minutes', $minutes));

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
            $this->addMessage(sprintf('Would delete %d subscribers with status ', $successful->count()) . MailchimpSubscriber::CHIMPLE_STATUS_SUCCESS);
        } else {
            foreach ($successful as $subscriber) {
                // remove this subscriber from the table
                $subscriber->delete();
                $success_deletes++;
            }

            $this->addMessage(sprintf('Deleted %d subscribers with status ', $success_deletes) . MailchimpSubscriber::CHIMPLE_STATUS_SUCCESS);
        }
        $this->currentStep = $success_deletes;
        $this->totalSteps = $success_deletes;

        // remove stale failed subscriptions older than 7 days
        $fail_datetime = new DateTime();
        $fail_datetime->modify('-7 days');

        $failed = MailchimpSubscriber::get()->filter([
                'Status' => MailchimpSubscriber::CHIMPLE_STATUS_FAIL,
                'Created:LessThan' => $fail_datetime->format('Y-m-d H:i:s')
        ]);

        if ($this->report_only) {
            $this->addMessage(sprintf('Would delete %d subscribers with status ', $failed->count()) . MailchimpSubscriber::CHIMPLE_STATUS_FAIL);
        } else {
            foreach ($failed as $failed_subscriber) {
                // remove this subscriber from the table
                $failed_subscriber->delete();
                $failed_deletes++;
            }

            $this->addMessage(sprintf('Deleted %d subscribers with status ', $failed_deletes) . MailchimpSubscriber::CHIMPLE_STATUS_FAIL);
        }
        $this->currentStep = $success_deletes + $failed_deletes;
        $this->totalSteps = $success_deletes + $failed_deletes;

        return true;
    }

    /**
     * Get next configured run time
     */
    private function getNextRunMinutes(): int
    {
        $minutes  = (int)$this->config()->get('run_in_minutes');
        if ($minutes <= 2) {
            // min every 2 minutes
            $minutes = 2;
        }

        return $minutes;
    }

    #[\Override]
    public function afterComplete(): void
    {
        $run_datetime = new DateTime();
        $minutes = $this->getNextRunMinutes();
        $run_datetime->modify(sprintf('+%s minutes', $minutes));
        // create a new job at the configured time
        singleton(QueuedJobService::class)->queueJob(new MailchimpCleanupJob($this->minutes_ago, $this->limit, $this->report_only), $run_datetime->format('Y-m-d H:i:s'));
    }
}
