<?php

namespace App\Domains\Commerce\Controllers;

use App\Domains\Commerce\Requests\StoreUserAddressRequest;
use App\Domains\Commerce\Requests\UpdateUserAddressRequest;
use App\Domains\Commerce\Services\UserAddressService;
use App\Domains\Shared\Controller\BaseController;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAddressController extends BaseController
{
    public function __construct(
        private readonly UserAddressService $userAddressService,
    ) {
        parent::__construct();
    }

    public function index(Request $request, ?Closure $builderCallback = null): JsonResponse
    {
        return response()->json($this->userAddressService->listForUser($request->user()->id));
    }

    public function create(StoreUserAddressRequest $request): JsonResponse
    {
        $data = $request->validated();
        $address = $this->userAddressService->storeForUser($request->user()->id, $data);

        return response()->json(['data' => ['id' => (string) $address->id]], 201);
    }

    public function show(string $id): JsonResponse
    {
        $a = $this->userAddressService->showForUser((string) auth()->id(), $id);

        return response()->json(['data' => [
            'id' => (string) $a->id,
            'recipient_name' => $a->recipient_name,
            'postal_code' => $a->postal_code,
            'street' => $a->street,
            'number' => $a->number,
            'complement' => $a->complement,
            'district' => $a->district,
            'city' => $a->city,
            'state' => $a->state,
            'is_default' => $a->is_default,
        ]]);
    }

    public function updateAddress(UpdateUserAddressRequest $request, string $id): JsonResponse
    {
        $this->userAddressService->updateForUser($request->user()->id, $id, $request->validated());

        return response()->json(['ok' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->userAddressService->destroyForUser((string) auth()->id(), $id);

        return response()->json(['ok' => true]);
    }
}
