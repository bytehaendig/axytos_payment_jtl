<?php

namespace Plugin\axytos_payment\frontend;

class AgreementController
{
    private $plugin;
    private $paymentMethod;
    private $cache;
    private string $path = '/axytos-agreement';

    public function __construct($plugin, $paymentMethod, $cache)
    {
        $this->plugin = $plugin;
        $this->paymentMethod = $paymentMethod;
        $this->cache = $cache;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    private function getAgreement()
    {
        $cacheId = $this->plugin->pluginCacheID . '_agreement';
        $agreement = $this->cache->get($cacheId);
        if ($agreement === false) {
            $api = $this->paymentMethod->createApiClient();
            $agreement = $api->getAgreement();
            $this->cache->set($cacheId, $agreement, [\CACHING_GROUP_PLUGIN, $this->plugin->pluginCacheGroup], 3600); // Cache for 1 hour
        }
        return $agreement;
    }

    public function getResponse($request, $args, $smarty)
    {
        $agreement = $this->getAgreement();
        return $smarty->assign('axytos_agreement', $agreement)
            ->getResponse(__DIR__ . '/template/agreement.tpl');
    }

    public function getLink($smarty): string
    {
        return $smarty->assign('axytos_agreement_link', $this->path)
            ->fetch(__DIR__ . '/template/agreement_link.tpl');
    }
}
