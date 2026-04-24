<?php

namespace App\Domains\Catalog\Controllers;

use App\Domains\Catalog\Requests\ProductAdminRequest;
use App\Domains\Catalog\Services\ProductAdminService;
use App\Domains\Shared\Controller\BaseController;
use Illuminate\Http\Request;

class ProductAdminController extends BaseController
{
    public function __construct(
        private readonly ProductAdminService $productAdminService,
    ) {
        $this->setACL('products', [
            'list' => ['index'],
            'read' => ['show'],
            'create' => ['store'],
            'edit' => ['update'],
            'delete' => ['destroy'],
        ]);
        parent::__construct();
        $this->setService($this->productAdminService);
        $this->setRequest('request', ProductAdminRequest::class);
    }

    public function store(Request $request)
    {
        $validated = $this->request($request)->validated();

        return response()->json($this->productAdminService->store($validated), 201);
    }

    public function update(Request $request, string $product)
    {
        $validated = $this->request($request)->validated();

        return response()->json($this->productAdminService->update($validated, $product));
    }

    public function destroy(string $id)
    {
        $this->productAdminService->destroy($id);

        return response()->noContent();
    }
}
