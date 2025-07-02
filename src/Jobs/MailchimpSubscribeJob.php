<?php

namespace NSWDPC\Chimple\Jobs;

use NSWDPC\Chimple\Models\MailchimpSubscriber;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use SilverStripe\Core\Config\Configurable;
use DateTime;

class MailchimpSubscribeJob extends AbstractQueuedJob implements QueuedJob
{
    use Configurable;

    private static $run_in_seconds = 60;
    private static $default_limit = 100;

    public function __construct($limit = 100, $report_only = 0)
    {
        $this->report_only = $report_only;
        $this->limit = (int)$limit;
    }

    public function getTitle()
    {
        $title = _t(
            __CLASS__ . '.TITLE',
            "Batch subscribe emails to Mailchimp"
        );
        $title .= " (limit:{$this->limit})";
        $title .= ($this->report_only ? " - report only" : "");
        return $title;
    }

    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    public function setup()
    {
        $this->totalSteps = 1;
    }

    private function getTotalResults($results)
    {
        $total = 0;
        foreach ($results as $key => $count) {
            $total += $count;
        }
        return $total;
    }

    private function getTotalNonFailedResults($results)
    {
        $copy = $results;
        unset($copy[ MailchimpSubscriber::CHIMPLE_STATUS_FAIL]);
        return $this->getTotalResults($copy);
    }

    /**
     * Process the job
     */
    public function process()
    {
        $results = MailchimpSubscriber::batch_subscribe($this->limit, $this->report_only);
        if ($this->report_only) {
            $this->addMessage("Report only: " . json_encode($results));
            $this->totalSteps = $this->currentStep = $this->getTotalResults($results);
        } elseif (is_array($results)) {
            foreach ($results as $status => $count) {
                $message = $status . ":" . $count;
                $this->addMessage($message);
            }
            $this->totalSteps = $this->getTotalResults($results);// total including failed
            $this->currentStep = $this->getTotalNonFailedResults($results);
        } else {
            $this->addMessage("Failed completely - check logs!");
            $this->totalSteps = $this->currentStep = 0;
        }
        $this->isComplete = true;
    }

    /**
     * Recreate the MailchimpSubscribeJob job for the next run
     */
    public function afterComplete()
    {
        $run_datetime = new DateTime();
        $seconds  = (int)$this->config()->get('run_in_seconds');
        if ($seconds <= 30) {
            // min every 30s
            $seconds = 30;
        }
        $run_datetime->modify("+{$seconds} seconds");
        singleton(QueuedJobService::class)->queueJob(
            new MailchimpSubscribeJob($this->limit, $this->report_only),
            $run_datetime->format('Y-m-d H:i:s')
        );
    }
}
