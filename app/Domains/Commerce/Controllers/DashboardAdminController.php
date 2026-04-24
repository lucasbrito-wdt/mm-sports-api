<?php

namespace App\Domains\Commerce\Controllers;

use App\Domains\Commerce\Services\DashboardAdminService;
use App\Domains\Shared\Controller\BaseController;
use Illuminate\Http\JsonResponse;

class DashboardAdminController extends BaseController
{
    public function __construct(
        private readonly DashboardAdminService $dashboardAdminService,
    ) {
        $this->setACL('dashboard', [
            'read' => ['summary'],
        ]);
        parent::__construct();
    }

    public function summary(): JsonResponse
    {
        return response()->json($this->dashboardAdminService->summary());
    }
}
