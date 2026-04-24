<?php

namespace App\Domains\Catalog\Controllers;

use App\Domains\Catalog\Services\CatalogFacetService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CatalogFacetController extends Controller
{
    public function __construct(
        private readonly CatalogFacetService $catalogFacetService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['facets' => $this->catalogFacetService->getFacets()]);
    }
}
