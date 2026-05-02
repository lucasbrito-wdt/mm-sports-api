<?php

namespace App\Domains\Catalog\Controllers\Admin;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Requests\Admin\CreateProductImageRequest;
use App\Domains\Catalog\Requests\Admin\UpdateProductImageRequest;
use App\Domains\Catalog\Services\ProductImageService;
use App\Domains\Shared\Traits\Dependencies;
use App\Domains\Shared\Traits\HasACL;
use App\Domains\Shared\Traits\ResolvesFormRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductImageController extends Controller
{
    use Dependencies, HasACL, ResolvesFormRequest;

    private readonly ProductImageService $imageService;

    public function __construct(ProductImageService $imageService)
    {
        $this->imageService = $imageService;
        $this->setACL('products', [
            'list'   => ['index'],
            'create' => ['store'],
            'edit'   => ['update'],
            'delete' => ['destroy'],
        ]);
        $this->bootACL();
    }

    public function index(Product $product): JsonResponse
    {
        return response()->json(['data' => $this->imageService->listByProduct($product->id)]);
    }

    public function store(CreateProductImageRequest $request, Product $product): JsonResponse
    {
        $img = $this->imageService->createForProduct($product->id, $request->validated());
        return response()->json(['data' => $img], 201);
    }

    public function update(UpdateProductImageRequest $request, Product $product, ProductImage $image): JsonResponse
    {
        abort_unless($image->product_id === $product->id, 404);
        $updated = $this->imageService->updateOne($image->id, $request->validated());
        return response()->json(['data' => $updated]);
    }

    public function destroy(Product $product, ProductImage $image): Response
    {
        abort_unless($image->product_id === $product->id, 404);
        $this->imageService->deleteOne($image->id);
        return response()->noContent();
    }
}
