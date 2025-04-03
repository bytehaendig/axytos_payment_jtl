<?php

namespace Plugin\axytos_payment\frontend;

class AgreementController
{
    private $plugin;
    private $paymentMethod;
    private string $path = '/axytos-agreement';

    public function __construct($plugin, $paymentMethod)
    {
        $this->plugin = $plugin;
        $this->paymentMethod = $paymentMethod;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getResponse($request, $args, $smarty)
    {
        $api = $this->paymentMethod->createApiClient();
        $agreement = $api->getAgreement();
        return $smarty->assign('axytos_agreement', $agreement)
            ->getResponse(__DIR__ . '/template/agreement.tpl');
    }

    public function getLink($smarty): string
    {
        return $smarty->assign('axytos_agreement_link', $this->path)
            ->fetch(__DIR__ . '/template/agreement_link.tpl');
    }
}
