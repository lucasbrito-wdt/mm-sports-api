<?php

namespace App\Domains\Catalog\Controllers;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Requests\ProductPersonalizationOptionAdminRequest;
use App\Domains\Catalog\Services\ProductPersonalizationOptionAdminService;
use App\Domains\Shared\Traits\Dependencies;
use App\Domains\Shared\Traits\HasACL;
use App\Domains\Shared\Traits\ResolvesFormRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductPersonalizationOptionAdminController extends Controller
{
    use Dependencies, HasACL, ResolvesFormRequest;

    public function __construct(
        private readonly ProductPersonalizationOptionAdminService $productPersonalizationOptionAdminService,
    ) {
        $this->setACL('products', [
            'personalization list' => ['index'],
            'personalization read' => ['show'],
            'personalization create' => ['store'],
            'personalization edit' => ['update'],
            'personalization delete' => ['destroy'],
        ]);
        $this->setService($this->productPersonalizationOptionAdminService);
        $this->setRequest('request', ProductPersonalizationOptionAdminRequest::class);
        $this->bootACL();
    }

    public function index(Request $request, Product $product): JsonResponse
    {
        return response()->json(
            $this->productPersonalizationOptionAdminService->indexForProduct($product->id, $request->all())
        );
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $this->request($request)->validated();

        return response()->json(
            $this->productPersonalizationOptionAdminService->storeForProduct($product->id, $validated),
            201
        );
    }

    public function show(Product $product, string $option): JsonResponse
    {
        return response()->json(
            $this->productPersonalizationOptionAdminService->showForProduct($product->id, $option)
        );
    }

    public function update(Request $request, Product $product, string $option): JsonResponse
    {
        $validated = $this->request($request)->validated();

        return response()->json(
            $this->productPersonalizationOptionAdminService->updateForProduct(
                $product->id,
                $option,
                $validated
            )
        );
    }

    public function destroy(Product $product, string $option)
    {
        $this->productPersonalizationOptionAdminService->destroyForProduct($product->id, $option);

        return response()->noContent();
    }
}
