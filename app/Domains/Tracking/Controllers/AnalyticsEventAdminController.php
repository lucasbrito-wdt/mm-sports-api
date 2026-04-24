<?php

namespace App\Domains\Tracking\Controllers;

use App\Domains\Shared\Controller\BaseController;
use App\Domains\Tracking\Services\AnalyticsEventAdminService;

class AnalyticsEventAdminController extends BaseController
{
    public function __construct(
        private readonly AnalyticsEventAdminService $analyticsEventAdminService,
    ) {
        $this->setACL('analytics', [
            'events list' => ['index'],
            'events read' => ['show'],
        ]);
        parent::__construct();
        $this->setService($this->analyticsEventAdminService);
    }
}
