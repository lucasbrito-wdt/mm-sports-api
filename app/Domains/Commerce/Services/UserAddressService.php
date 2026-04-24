<?php

namespace App\Domains\Commerce\Services;

use App\Domains\Commerce\Models\UserAddress;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Support\Str;

class UserAddressService extends BaseService
{
    public function __construct(
        private readonly UserAddress $userAddress,
    ) {
        $this->setModel($this->userAddress);
    }

    public function listForUser(string $userId): array
    {
        $rows = $this->userAddress->newQuery()->where('user_id', $userId)->orderByDesc('is_default')->get();

        return ['data' => $rows->map(fn (UserAddress $a) => $this->transform($a))->all()];
    }

    public function storeForUser(string $userId, array $data): UserAddress
    {
        $data['id'] = (string) Str::ulid();
        $data['user_id'] = $userId;
        if (! empty($data['is_default'])) {
            $this->userAddress->newQuery()->where('user_id', $userId)->update(['is_default' => false]);
        }

        return $this->userAddress->newQuery()->create($data);
    }

    public function updateForUser(string $userId, string $id, array $data): UserAddress
    {
        $address = $this->userAddress->newQuery()->where('user_id', $userId)->where('id', $id)->firstOrFail();
        if (! empty($data['is_default'])) {
            $this->userAddress->newQuery()->where('user_id', $userId)->update(['is_default' => false]);
        }
        $address->update($data);

        return $address->fresh();
    }

    public function destroyForUser(string $userId, string $id): bool
    {
        $q = $this->userAddress->newQuery()->where('user_id', $userId)->where('id', $id);
        if ($q->exists()) {
            return (bool) $q->delete();
        }

        return false;
    }

    public function showForUser(string $userId, string $id): UserAddress
    {
        return $this->userAddress->newQuery()->where('user_id', $userId)->where('id', $id)->firstOrFail();
    }

    private function transform(UserAddress $a): array
    {
        return [
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
        ];
    }
}
