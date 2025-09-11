<?php

namespace Plugin\axytos_payment\helpers;

use JTL\Shop;
use JTL\Cron\JobInterface;
use JTL\Cron\Queue;
use JTL\Router\Controller\Backend\CronController;
use Plugin\axytos_payment\helpers\CronJobRunActions;
use JTL\DB\DbInterface;

class CronHelper
{
    private const CRON_TYPE = 'plugin:axytos_payment.updates';
    private const FREQUENCY = 1; // in h
    private const START_TIME = '02:00';

    public function availableCronjobType(array &$args): void
    {
        $logger = Shop::Container()->getLogService();
        $logger->debug('Axytos availableCronjobType called', ['args' => $args]);
        
        if (!\in_array(self::CRON_TYPE, $args['jobs'], true)) {
            $args['jobs'][] = self::CRON_TYPE;
            $logger->info('Added Axytos cron job type to available jobs', ['type' => self::CRON_TYPE]);
        }
    }

    public function mappingCronjobType(array &$args): void
    {
        /** @var string $type */
        $type = $args['type'];
        $logger = Shop::Container()->getLogService();
        $logger->debug('Axytos mappingCronjobType called', ['type' => $type]);
        
        if ($type === self::CRON_TYPE) {
            $args['mapping'] = CronJobRunActions::class;
            $logger->info('Mapped Axytos cron job type to class', [
                'type' => self::CRON_TYPE,
                'class' => CronJobRunActions::class
            ]);
        }
    }

    public function installCron(): void
    {
        try {
            $controller = Shop::Container()->get(CronController::class);
            $result = $controller->addQueueEntry([
                'type'      => self::CRON_TYPE,
                'frequency' => self::FREQUENCY,
                'time'      => self::START_TIME,
                'date'      => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
            
            $logger = Shop::Container()->getLogService();
            $logger->info('Axytos cron job installation attempted', [
                'type' => self::CRON_TYPE,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            $logger = Shop::Container()->getLogService();
            $logger->error('Failed to install Axytos cron job: {error}', [
                'error' => $e->getMessage(),
                'type' => self::CRON_TYPE
            ]);
        }
    }

    public function cronJobExists(): bool
    {
        try {
            $controller = Shop::Container()->get(CronController::class);
            $cron = \array_filter($controller->getJobs(), static function (JobInterface $job) {
                return $job->getType() === self::CRON_TYPE;
            });
            
            return \count($cron) > 0;
        } catch (\Exception $e) {
            $logger = Shop::Container()->getLogService();
            $logger->error('Failed to check Axytos cron job existence: {error}', [
                'error' => $e->getMessage(),
                'type' => self::CRON_TYPE
            ]);
            return false;
        }
    }

    public function installCronIfMissing(): void
    {
        if (!$this->cronJobExists()) {
            $this->installCron();
        } else {
            $logger = Shop::Container()->getLogService();
            $logger->debug('Axytos cron job already exists, skipping installation', [
                'type' => self::CRON_TYPE
            ]);
        }
    }

    public function uninstallCron(): void
    {
        $controller = Shop::Container()->get(CronController::class);
        $cron       = \array_filter($controller->getJobs(), static function (JobInterface $job) {
            return $job->getType() === self::CRON_TYPE;
        });

        if (\count($cron) !== 0) {
            $controller->deleteQueueEntry(\array_shift($cron)->getCronID());
        }
    }

    public function resetStuckCronJobs(): array
    {
        $db = Shop::Container()->getDB();
        $logger = Shop::Container()->getLogService();
        
        try {
            // First, count stuck Axytos cron jobs before reset using JTL's criteria (QUEUE_MAX_STUCK_HOURS = 1 hour)
            $stuckJobs = $db->getCollection(
                "SELECT jq.jobQueueID, c.jobType 
                 FROM tjobqueue jq 
                 JOIN tcron c ON c.cronID = jq.cronID 
                 WHERE c.jobType LIKE '%axytos%' 
                 AND jq.isRunning = 1 
                 AND jq.lastStart IS NOT NULL 
                 AND DATE_SUB(NOW(), INTERVAL :ntrvl Hour) > jq.lastStart",
                ['ntrvl' => \QUEUE_MAX_STUCK_HOURS]
            );
            
            $foundCount = count($stuckJobs);
            if ($foundCount === 0) {
                return ['reset_count' => 0, 'found_count' => 0];
            }
            
            // Use JTL-Shop's built-in Queue::unStuckQueues() method to reset ALL stuck jobs
            // Note: This will reset ALL stuck jobs system-wide, not just Axytos jobs
            $queue = Shop::Container()->get(Queue::class);
            $totalReset = $queue->unStuckQueues();
            
            // Count how many of the reset jobs were Axytos jobs by checking if they're no longer stuck
            $stillStuckJobs = $db->getCollection(
                "SELECT jq.jobQueueID 
                 FROM tjobqueue jq 
                 JOIN tcron c ON c.cronID = jq.cronID 
                 WHERE c.jobType LIKE '%axytos%' 
                 AND jq.isRunning = 1 
                 AND jq.lastStart IS NOT NULL 
                 AND DATE_SUB(NOW(), INTERVAL :ntrvl Hour) > jq.lastStart",
                ['ntrvl' => \QUEUE_MAX_STUCK_HOURS]
            );
            
            $axytosResetCount = $foundCount - count($stillStuckJobs);
            
            $logger->info('Reset stuck {axytos_reset} Axytos cron jobs', [
                'axytos_reset' => $axytosResetCount,
            ]);
            
            return [
                'reset_count' => $axytosResetCount,
                'found_count' => $foundCount
            ];
            
        } catch (\Exception $e) {
            $logger->error('Failed to reset stuck Axytos cron jobs: {error}', [
                'error' => $e->getMessage()
            ]);
            throw $e; // Re-throw so StatusHandler can handle the error message
        }
    }

    public function getNextCronRun(): ?string
    {
        $db = Shop::Container()->getDB();
        
        // Get the next scheduled run for Axytos cron jobs from JTL's cron system
        $cronJob = $db->getSingleObject(
            "SELECT nextStart FROM tcron WHERE jobType LIKE '%axytos%' ORDER BY nextStart ASC LIMIT 1"
        );
        
        return $cronJob->nextStart ?? null;
    }

    public function getLastCronRun(): ?string
    {
        $db = Shop::Container()->getDB();
        
        // Get the most recent completion time from tcron for Axytos jobs
        $lastJob = $db->getSingleObject(
            "SELECT MAX(lastFinish) as lastFinish 
             FROM tcron 
             WHERE jobType LIKE '%axytos%' 
             AND lastFinish IS NOT NULL"
        );
        
        return $lastJob->lastFinish ?? null;
    }

    public function getCronStatus(): array
    {
        $db = Shop::Container()->getDB();
        
        // Check if any Axytos cron jobs are currently running
        $runningJobs = $db->getSingleObject(
            "SELECT COUNT(*) as running_count 
             FROM tjobqueue jq 
             JOIN tcron c ON c.cronID = jq.cronID 
             WHERE c.jobType LIKE '%axytos%' 
             AND jq.isRunning = 1"
        );
        
        // Check if any jobs are stuck (running for too long) - use JTL's QUEUE_MAX_STUCK_HOURS constant
        $stuckJobs = $db->getSingleObject(
            "SELECT COUNT(*) as stuck_count 
             FROM tjobqueue jq 
             JOIN tcron c ON c.cronID = jq.cronID 
             WHERE c.jobType LIKE '%axytos%' 
             AND jq.isRunning = 1 
             AND jq.lastStart IS NOT NULL 
             AND DATE_SUB(NOW(), INTERVAL :ntrvl HOUR) > jq.lastStart",
            ['ntrvl' => \QUEUE_MAX_STUCK_HOURS]
        );
        
        $isRunning = (int)($runningJobs->running_count ?? 0) > 0;
        $hasStuck = (int)($stuckJobs->stuck_count ?? 0) > 0;
        
        return [
            'is_running' => $isRunning,
            'has_stuck' => $hasStuck,
            'running_count' => (int)($runningJobs->running_count ?? 0),
            'stuck_count' => (int)($stuckJobs->stuck_count ?? 0)
        ];
    }
}
