<?php

namespace App\Domains\Tracking\Controllers;

use App\Domains\Shared\Controller\BaseController;
use App\Domains\Tracking\Services\OrderStatusTransitionAdminService;

class OrderStatusTransitionAdminController extends BaseController
{
    public function __construct(
        private readonly OrderStatusTransitionAdminService $orderStatusTransitionAdminService,
    ) {
        $this->setACL('order', [
            'status transitions list' => ['index'],
            'status transitions read' => ['show'],
        ]);
        parent::__construct();
        $this->setService($this->orderStatusTransitionAdminService);
    }
}
