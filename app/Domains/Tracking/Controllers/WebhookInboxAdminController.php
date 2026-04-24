<?php

namespace App\Domains\Tracking\Controllers;

use App\Domains\Shared\Controller\BaseController;
use App\Domains\Tracking\Services\WebhookInboxAdminService;

class WebhookInboxAdminController extends BaseController
{
    public function __construct(
        private readonly WebhookInboxAdminService $webhookInboxAdminService,
    ) {
        $this->setACL('webhook', [
            'inbox list' => ['index'],
            'inbox read' => ['show'],
        ]);
        parent::__construct();
        $this->setService($this->webhookInboxAdminService);
    }
}
