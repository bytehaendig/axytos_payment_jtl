<?php

namespace Plugin\axytos_payment\helpers;

use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;
use JTL\Shop;
use Plugin\axytos_payment\paymentmethod\AxytosPaymentMethod;
use Plugin\axytos_payment\helpers\ActionHandler;
use Plugin\axytos_payment\helpers\Utils;
use Exception;

class UpdatesCronJob extends Job
{
    public function start(QueueEntry $entry): JobInterface
    {
        parent::start($entry);
        $finished = $this->processPendingActions();
        $this->setFinished($finished);
        return $this;
    }

    /** @return true if task is finished */
    private function processPendingActions(): bool
    {
        try {
            // Setup: Get payment method and create action handler
            $paymentMethod = $this->getAxytosPaymentMethod();
            if ($paymentMethod === null) {
                return true; // Setup failed, but consider finished to avoid immediate retry
            }

            $actionHandler = $paymentMethod->createActionHandler();
            
            // Execute action processing
            $result = $actionHandler->processAllPendingActions();
            $this->logResults($paymentMethod->getLogger(), $result, $actionHandler);

            return true; // Always return true - the action handler manages its own retry logic

        } catch (Exception $e) {
            $logger = Shop::Container()->getLogService();
            $logger->error('Axytos cron job failed: {error}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return true; // Return true to avoid JTL retrying the cron job immediately
        }
    }

    private function logResults($logger, array $result, ActionHandler $actionHandler): void
    {
        // Log processing results
        if ($result['processed'] > 0 || $result['failed'] > 0) {
            $logger->info('Axytos cron job processed {processed} orders, {failed} failed', [
                'processed' => $result['processed'],
                'failed' => $result['failed']
            ]);
        }

        // Log broken actions that need attention
        $brokenCount = $actionHandler->getOrdersWithBrokenActionsCount();
        if ($brokenCount > 0) {
            $logger->warning('Axytos has {brokenCount} orders with permanently failed actions requiring manual intervention', [
                'brokenCount' => $brokenCount
            ]);
        }
    }

    private function getAxytosPaymentMethod(): ?AxytosPaymentMethod
    {
        try {
            $db = Shop::Container()->getDB();
            return Utils::createPaymentMethod($db);
        } catch (Exception $e) {
            return null;
        }
    }
}
