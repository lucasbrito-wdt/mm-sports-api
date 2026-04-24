<?php

namespace App\Domains\Catalog\Controllers;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Requests\ProductVariantAdminRequest;
use App\Domains\Catalog\Services\ProductVariantAdminService;
use App\Domains\Shared\Traits\Dependencies;
use App\Domains\Shared\Traits\HasACL;
use App\Domains\Shared\Traits\ResolvesFormRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantAdminController extends Controller
{
    use Dependencies, HasACL, ResolvesFormRequest;

    public function __construct(
        private readonly ProductVariantAdminService $productVariantAdminService,
    ) {
        $this->setACL('products', [
            'variants list' => ['index'],
            'variants read' => ['show'],
            'variants create' => ['store'],
            'variants edit' => ['update'],
            'variants delete' => ['destroy'],
        ]);
        $this->setService($this->productVariantAdminService);
        $this->setRequest('request', ProductVariantAdminRequest::class);
        $this->bootACL();
    }

    public function index(Request $request, Product $product): JsonResponse
    {
        return response()->json(
            $this->productVariantAdminService->indexForProduct($product->id, $request->all())
        );
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $this->request($request)->validated();

        return response()->json(
            $this->productVariantAdminService->storeForProduct($product->id, $validated),
            201
        );
    }

    public function show(Product $product, string $variant): JsonResponse
    {
        return response()->json(
            $this->productVariantAdminService->showForProduct($product->id, $variant)
        );
    }

    public function update(Request $request, Product $product, string $variant): JsonResponse
    {
        $validated = $this->request($request)->validated();

        return response()->json(
            $this->productVariantAdminService->updateForProduct($product->id, $variant, $validated)
        );
    }

    public function destroy(Product $product, string $variant)
    {
        $this->productVariantAdminService->destroyForProduct($product->id, $variant);

        return response()->noContent();
    }
}
