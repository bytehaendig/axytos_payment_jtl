<?php

namespace Plugin\axytos_payment\helpers;

use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;

class UpdatesCronJob extends Job
{
    public function start(QueueEntry $entry): JobInterface
    {
        parent::start($entry);
        $finished = $this->confirmUnconfirmedOrders();
        // if ($finished) {
        //     $finished = $this->otherTask();
        // }
        $this->setFinished($finished);
        return $this;
    }

    /** @return true if task is finished */
    private function confirmUnconfirmedOrders(): bool
    {
        // find all orders that have this payment method and are not confirmed
        // then confirm them - probably have to re-run precheck first
        // because saved precheck-response is valid only for limited time
    }
}
