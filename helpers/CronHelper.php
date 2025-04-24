<?php

namespace Plugin\axytos_payment\helpers;

use JTL\Shop;
use JTL\Cron\JobInterface;
use JTL\Router\Controller\Backend\CronController;
use Plugin\axytos_payment\helpers\UpdatesCronJob;

class CronHelper
{
    private const CRON_TYPE = 'plugin:axytos_payment.updates';
    private const FREQENCY = 24; // in h
    private const START_TIME = '02:00';

    public function availableCronjobType(array &$args): void
    {
        if (!\in_array(self::CRON_TYPE, $args['jobs'], true)) {
            $args['jobs'][] = self::CRON_TYPE;
        }
    }

    public function mappingCronjobType(array &$args): void
    {
        /** @var string $type */
        $type = $args['type'];
        if ($type === self::CRON_TYPE) {
            $args['mapping'] = UpdatesCronJob::class;
        }
    }

    public function installCron(): void
    {
        $controller = Shop::Container()->get(CronController::class);
        $controller->addQueueEntry([
            'type'      => self::CRON_TYPE,
            'frequency' => $this->FREQENCY,
            'time'      => $this->START_TIME,
            'date'      => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
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
