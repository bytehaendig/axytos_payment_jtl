<?php

namespace Plugin\axytos_payment\helpers;

use JTL\Shop;
use JTL\Cron\JobInterface;
use JTL\Router\Controller\Backend\CronController;
use Plugin\axytos_payment\helpers\UpdatesCronJob;

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
            $args['mapping'] = UpdatesCronJob::class;
            $logger->info('Mapped Axytos cron job type to class', [
                'type' => self::CRON_TYPE,
                'class' => UpdatesCronJob::class
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
}
