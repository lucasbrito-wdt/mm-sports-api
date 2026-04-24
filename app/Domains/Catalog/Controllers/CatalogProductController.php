<?php

namespace App\Domains\Catalog\Controllers;

use App\Domains\Catalog\Requests\CatalogListRequest;
use App\Domains\Catalog\Services\CatalogProductSearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CatalogProductController extends Controller
{
    public function __construct(
        private readonly CatalogProductSearchService $catalogProductSearchService,
    ) {}

    public function index(CatalogListRequest $request): JsonResponse
    {
        $filters = $request->query();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 24);

        $results = $this->catalogProductSearchService->searchProducts($filters, $page, $perPage);

        return response()->json(['data' => $results]);
    }
}
