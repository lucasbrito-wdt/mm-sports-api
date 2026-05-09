<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;

class CustomerAdminService extends BaseService
{
    public function __construct(private readonly User $user)
    {
        $this->setModel($this->user);
    }

    public function index(array $options = [], ?\Closure $builderCallback = null)
    {
        return parent::index($options, function (Builder $query) {
            $query
                ->whereHas('roles', fn (Builder $q) => $q->whereNot('slug', 'admin'))
                ->withCount('orders')
                ->orderBy('created_at', 'desc');
        });
    }

    public function show(string $id)
    {
        return $this->user->withCount('orders')->with('userAddresses')->findOrFail($id);
    }

    public function updateCustomer(string $id, array $data): User
    {
        $user = $this->user->findOrFail($id);
        $user->update($data);

        return $user->fresh()->loadCount('orders')->load('userAddresses');
    }
}
