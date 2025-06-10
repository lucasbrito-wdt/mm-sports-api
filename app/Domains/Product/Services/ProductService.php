<?php

namespace App\Domains\Product\Services;

use App\Domains\Product\Models\Product;
use App\Domains\Shared\Services\BaseService;

class ProductService extends BaseService
{
    public function __construct(private readonly Product $product)
    {
        $this->setModel($this->product);
    }

    // 👉 methods
    public function listarCategory($options) {
		$data = \App\Domains\Category\Models\Category::query()->paginate($options['per_page'] ?? 15);
		return [
			'data' => $data->items(),
			'total' => $data->total(),
			'page' => $data->currentPage(),
		];
	}
}
