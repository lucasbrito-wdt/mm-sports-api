<?php

namespace App\Domains\Commerce\Controllers;

use App\Domains\Commerce\Requests\UpdateOrderAdminRequest;
use App\Domains\Commerce\Services\OrderAdminService;
use App\Domains\Shared\Controller\BaseController;
use Illuminate\Http\Request;

class OrderAdminController extends BaseController
{
    public function __construct(
        private readonly OrderAdminService $orderAdminService,
    ) {
        $this->setACL('orders', [
            'list' => ['index'],
            'read' => ['show'],
            'update' => ['update'],
        ]);
        parent::__construct();
        $this->setService($this->orderAdminService);
        $this->setRequest('request', UpdateOrderAdminRequest::class);
    }

    public function update(Request $request, string $order)
    {
        $validated = $this->request($request)->validated();
        if ($validated === []) {
            return response()->json(
                $this->orderAdminService->show($order)
            );
        }

        return response()->json(
            $this->orderAdminService->updateByAdmin($order, $validated)
        );
    }
}
