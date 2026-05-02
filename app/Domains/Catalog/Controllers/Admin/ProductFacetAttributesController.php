<?php

namespace App\Domains\Catalog\Controllers\Admin;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Requests\Admin\SyncProductFacetAttributesRequest;
use App\Domains\Catalog\Services\ProductFacetAttributeService;
use App\Domains\Shared\Traits\Dependencies;
use App\Domains\Shared\Traits\HasACL;
use App\Domains\Shared\Traits\ResolvesFormRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ProductFacetAttributesController extends Controller
{
    use Dependencies, HasACL, ResolvesFormRequest;

    public function __construct(private readonly ProductFacetAttributeService $facetService)
    {
        $this->setACL('products', [
            'list' => ['show'],
            'edit' => ['update'],
        ]);
        $this->bootACL();
        $this->setService($this->facetService);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json(['data' => ['value_ids' => $this->facetService->listValueIds($product)]]);
    }

    public function update(SyncProductFacetAttributesRequest $request, Product $product): JsonResponse
    {
        $this->facetService->sync($product, $request->validated('value_ids'));

        return response()->json(['data' => ['value_ids' => $this->facetService->listValueIds($product)]]);
    }
}
