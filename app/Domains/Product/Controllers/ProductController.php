<?php

namespace App\Domains\Product\Controllers;

use App\Domains\Shared\Controller\BaseController;
use App\Domains\Product\Services\ProductService;
use App\Domains\Product\Requests\ProductRequest;

class ProductController extends BaseController
{
    public function __construct(private readonly ProductService $service)
    {
        $this->setACL('product', [
            'list' => ['product.index'],
            'create' => ['product.store'],
            'edit'=> ['product.update'],
            'delete' => ['product.destroy']
        ]);
        parent::__construct();
        $this->setService($this->service);
        $this->setRequest('request', ProductRequest::class);
    }

    // 👉 methods
    public function listarCategory(Request $request) {
		$options = $request->all();
		return $this->service->listarCategory($options);
	}
}
