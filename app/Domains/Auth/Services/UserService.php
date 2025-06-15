<?php

namespace App\Domains\Auth\Services;

use App\Domains\Shared\Utils\IntHelper;
use App\Domains\ACL\Models\Role;
use App\Domains\Auth\Models\User;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use DB;

class UserService extends BaseService
{
    public function __construct(
        private readonly User $user,
        private readonly Role $role,
    ) {
        $this->setModel($this->user);
    }

    public function index(array $options = [], ?\Closure $builderCallback = null)
    {
        return parent::index($options, function ($query) use ($options) {
            $query->whereHas('belongsToManyRoles', function (Builder $query) {
                $query->whereNot('slug', 'admin');
            })
                ->when(isset($options['sort_by']), function (Builder $query) use ($options) {
                    $query->orderBy($options['sort_by'], $options['sort_order'] ?? 'asc');
                });
        });
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $user = $this->user->create($data);

            if (env('CDF_EMAIL_VERIFICATION', false)) {
                $user->sendEmailVerificationNotification();
            } else {
                $user->markEmailAsVerified();
            }

            $user->assignRole($data['role']);

            return $user;
        });
    }

    public function update(array $data, string $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $user = $this->findById($id);
            $user->update($data);
            $user->syncRoles($data['role']);

            return $user;
        });
    }

    public function roles($options)
    {
        $data = $this->role
            ->select([
                'name',
                'slug',
            ])
            ->paginate(IntHelper::tryParser($options['per_page'] ?? 15) ?? 15);

        return [
            'data' => $data->items(),
            'total' => $data->total(),
            'page' => $data->currentPage(),
        ];
    }
}
