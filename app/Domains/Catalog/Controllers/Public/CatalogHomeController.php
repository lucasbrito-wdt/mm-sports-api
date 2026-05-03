<?php

namespace App\Domains\Catalog\Controllers\Public;

use App\Domains\Catalog\Services\ProductCatalogService;
use App\Domains\Shared\Controller\BaseController;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogHomeController extends BaseController
{
    public function __construct(
        private readonly ProductCatalogService $productCatalogService,
    ) {
        parent::__construct();
    }

    public function index(Request $request, ?Closure $builderCallback = null): JsonResponse
    {
        $rawBlocks = $request->query('categories');
        $categoryBlocks = null;
        if ($rawBlocks !== null && $rawBlocks !== '' && is_numeric($rawBlocks)) {
            $categoryBlocks = max(2, min(5, (int) $rawBlocks));
        }

        $perSection = (int) $request->query(
            'per_section',
            (int) config('mm_storefront.home.products_per_section', 12),
        );
        $destaquesLimit = (int) $request->query(
            'destaques_limit',
            (int) config('mm_storefront.home.destaques_limit', 12),
        );

        $payload = $this->productCatalogService->homeShowcase(
            $categoryBlocks,
            max(1, min(48, $perSection)),
            max(1, min(48, $destaquesLimit)),
        );

        return response()->json($payload);
    }
}
