<?php

namespace App\Domains\Catalog\Controllers\Public;

use App\Domains\Catalog\Services\CategoryService;
use App\Domains\Shared\Controller\BaseController;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCategoryController extends BaseController
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {
        parent::__construct();
    }

    public function index(Request $request, ?Closure $builderCallback = null): JsonResponse
    {
        return response()->json([
            'data' => $this->categoryService->listPublic(),
        ]);
    }

    public function tree(): JsonResponse
    {
        return response()->json([
            'data' => $this->categoryService->treePublic(),
        ]);
    }
}
