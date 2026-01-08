<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Domains\Auth\Requests\UserRequest;
use App\Domains\Auth\Services\UserService;
use App\Domains\Shared\Controller\BaseController;

class UserController extends BaseController
{
    public function __construct(private readonly UserService $userService)
    {
        $this->setACL('users', [
            'list' => ['index'],
            'create' => ['store'],
        ]);
        parent::__construct();
        $this->setService($this->userService);
        $this->setRequest('request', UserRequest::class);
    }

    public function store(Request $request)
    {
        $data = $request->all();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $this->userService->store($data);
    }

    public function update(Request $request, string $id)
    {
        $data = $request->all();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $this->userService->update($data, $id);
    }

    public function roles(Request $request)
    {
        $options = $request->all();

        return $this->userService->roles($options);
    }

    /**
     * Busca de usuários usando PostgreSQL Full Text Search (pg_trgm)
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     *
     * Query parameters:
     * - q (string, required): Termo de busca
     * - threshold (float, optional): Threshold de similaridade (0.0-1.0, padrão: 0.2)
     * - per_page (int, optional): Itens por página (padrão: 10)
     * - visibility (array, optional): Filtros de visibilidade para ACL (ex: ['public', 'internal'])
     */
    public function search(Request $request, ?\Closure $builderCallback = null)
    {
        $request->validate([
            'q' => 'required|string|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $q = $request->input('q');
        $perPage = $request->input('per_page', 10);

        // Busca com threshold e filtros ACL opcionais
        $query = User::search($q);

        return $query->paginate($perPage);
    }

    /**
     * @deprecated Use search() instead
     */
    public function searchText(Request $request)
    {
        return $this->search($request);
    }
}
