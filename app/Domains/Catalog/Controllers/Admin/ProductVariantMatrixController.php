<?php

namespace App\Domains\Catalog\Controllers\Admin;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Requests\Admin\GenerateVariantMatrixRequest;
use App\Domains\Catalog\Services\GenerateVariantMatrixService;
use App\Domains\Shared\Traits\Dependencies;
use App\Domains\Shared\Traits\HasACL;
use App\Domains\Shared\Traits\ResolvesFormRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantMatrixController extends Controller
{
    use Dependencies, HasACL, ResolvesFormRequest;

    public function __construct(
        private readonly GenerateVariantMatrixService $generateVariantMatrixService,
    ) {
        $this->setACL('products', [
            'variants create' => ['generate'],
        ]);
        $this->setRequest('request', GenerateVariantMatrixRequest::class);
        $this->bootACL();
    }

    public function generate(Request $request, Product $product): JsonResponse
    {
        $validated = $this->request($request)->validated();
        $map = [];
        foreach ($validated['axes'] as $axis) {
            $map[(string) $axis['attribute_id']] = array_map('strval', $axis['value_ids']);
        }

        $this->generateVariantMatrixService->handle($product, $map);

        return response()->json([
            'data' => $product->refresh()->load('variants')->variants,
        ]);
    }
}
