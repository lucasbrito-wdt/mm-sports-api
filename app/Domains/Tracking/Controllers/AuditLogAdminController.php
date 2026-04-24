<?php

namespace App\Domains\Tracking\Controllers;

use App\Domains\Shared\Controller\BaseController;
use App\Domains\Tracking\Services\AuditLogAdminService;

class AuditLogAdminController extends BaseController
{
    public function __construct(
        private readonly AuditLogAdminService $auditLogAdminService,
    ) {
        $this->setACL('audit', [
            'logs list' => ['index'],
            'logs read' => ['show'],
        ]);
        parent::__construct();
        $this->setService($this->auditLogAdminService);
    }
}
