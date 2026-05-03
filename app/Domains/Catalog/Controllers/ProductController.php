<?php

namespace App\Domains\Catalog\Controllers;

use App\Domains\Catalog\Services\ProductCatalogService;
use App\Domains\Reviews\Services\ProductReviewService;
use App\Domains\Shared\Controller\BaseController;
use App\Domains\Tracking\Services\AnalyticsService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ProductController extends BaseController
{
    public function __construct(
        private readonly ProductCatalogService $productCatalogService,
        private readonly AnalyticsService $analyticsService,
        private readonly ProductReviewService $productReviewService,
    ) {
        parent::__construct();
    }

    public function index(Request $request, ?Closure $builderCallback = null): JsonResponse
    {
        $rawSearch = $request->query('q');
        if (! is_string($rawSearch) || trim($rawSearch) === '') {
            $rawSearch = $request->query('search');
        }
        $search = is_string($rawSearch) ? trim($rawSearch) : null;
        if ($search === '') {
            $search = null;
        }

        $rawCategory = $request->query('category_id');
        $categoryId = is_string($rawCategory) && $rawCategory !== '' ? $rawCategory : null;

        $out = $this->productCatalogService->listPublished($search, $categoryId);
        try {
            $this->analyticsService->track(
                'product_list_viewed',
                auth('api')->id(),
                array_filter(['category' => $request->query('category')]),
                'api',
                $request
            );
        } catch (InvalidArgumentException) {
        }
        if ($search !== null && $search !== '') {
            try {
                $this->analyticsService->track(
                    'search_executed',
                    auth('api')->id(),
                    ['query' => mb_substr($search, 0, 120)],
                    'api',
                    $request
                );
            } catch (InvalidArgumentException) {
            }
        }

        return response()->json($out);
    }

    public function show(string $id): JsonResponse
    {
        $data = $this->productCatalogService->showPublished($id);
        if ($data === null) {
            abort(404);
        }
        $variantIds = array_column($data['variants'] ?? [], 'id');
        try {
            $this->analyticsService->track(
                'product_viewed',
                auth('api')->id(),
                array_filter([
                    'product_id' => $data['id'] ?? $id,
                    'product_variant_ids' => $variantIds,
                ]),
                'api',
                request()
            );
        } catch (InvalidArgumentException) {
        }

        return response()->json($data);
    }

    public function reviews(string $id): JsonResponse
    {
        if ($this->productCatalogService->showPublished($id) === null) {
            abort(404);
        }

        return response()->json($this->productReviewService->listApprovedForProduct($id));
    }
}
