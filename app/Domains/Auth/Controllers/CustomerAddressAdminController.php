<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Commerce\Requests\StoreUserAddressRequest;
use App\Domains\Commerce\Requests\UpdateUserAddressRequest;
use App\Domains\Commerce\Services\UserAddressService;
use App\Domains\Shared\Traits\Dependencies;
use App\Domains\Shared\Traits\HasACL;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CustomerAddressAdminController extends Controller
{
    use Dependencies, HasACL;

    public function __construct(
        private readonly UserAddressService $userAddressService,
    ) {
        $this->setACL('customers', [
            'list' => ['list'],
            'update' => ['store', 'update', 'destroy'],
        ]);
        $this->bootACL();
    }

    public function list(string $customer): JsonResponse
    {
        return response()->json($this->userAddressService->listForUser($customer));
    }

    public function store(StoreUserAddressRequest $request, string $customer): JsonResponse
    {
        $address = $this->userAddressService->storeForUser($customer, $request->validated());

        return response()->json([
            'data' => $this->userAddressService->toApiArray($address),
        ], 201);
    }

    public function update(UpdateUserAddressRequest $request, string $customer, string $address): JsonResponse
    {
        $model = $this->userAddressService->updateForUser($customer, $address, $request->validated());

        return response()->json([
            'data' => $this->userAddressService->toApiArray($model),
        ]);
    }

    public function destroy(string $customer, string $address): JsonResponse
    {
        $deleted = $this->userAddressService->destroyForUser($customer, $address);
        if (! $deleted) {
            abort(404);
        }

        return response()->json(['ok' => true]);
    }
}
