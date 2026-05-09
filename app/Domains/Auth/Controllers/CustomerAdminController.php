<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Requests\CustomerAdminRequest;
use App\Domains\Auth\Services\CustomerAdminService;
use App\Domains\Shared\Controller\BaseController;
use Illuminate\Http\Request;

class CustomerAdminController extends BaseController
{
    public function __construct(
        private readonly CustomerAdminService $customerAdminService,
    ) {
        $this->setACL('customers', [
            'list' => ['index'],
            'read' => ['show'],
            'update' => ['update'],
        ]);
        parent::__construct();
        $this->setService($this->customerAdminService);
        $this->setRequest('request', CustomerAdminRequest::class);
    }

    public function update(Request $request, string $customer)
    {
        $validated = $this->request($request)->validated();

        $mapped = array_filter([
            'name' => $validated['name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'cpf' => $validated['document'] ?? null,
        ], fn ($v) => $v !== null);

        return response()->json(
            $this->customerAdminService->updateCustomer($customer, $mapped)
        );
    }
}
