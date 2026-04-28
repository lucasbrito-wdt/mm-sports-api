<?php

namespace App\Domains\Catalog\Controllers\Admin;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Requests\Admin\StoreCategoryRequest;
use App\Domains\Catalog\Requests\Admin\UpdateCategoryRequest;
use App\Domains\Catalog\Services\CategoryService;
use App\Domains\Shared\Traits\Dependencies;
use App\Domains\Shared\Traits\HasACL;
use App\Domains\Shared\Traits\ResolvesFormRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    use Dependencies, HasACL, ResolvesFormRequest;

    public function __construct(
        private readonly CategoryService $categoryService,
    ) {
        $this->setACL('categories', [
            'list' => ['index', 'tree'],
            'create' => ['store'],
            'edit' => ['update'],
            'delete' => ['destroy'],
        ]);
        $this->bootACL();
    }

    public function tree(): JsonResponse
    {
        return response()->json([
            'data' => $this->categoryService->buildTree(),
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->setRequest('request', StoreCategoryRequest::class);
        $validated = $this->request($request)->validated();

        return response()->json([
            'data' => $this->categoryService->create($validated),
        ], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $this->setRequest('request', UpdateCategoryRequest::class);
        $validated = $this->request($request)->validated();

        return response()->json([
            'data' => $this->categoryService->update($validated, (string) $category->id),
        ]);
    }

    public function destroy(Category $category): Response
    {
        $this->categoryService->delete((string) $category->id);

        return response()->noContent();
    }
}
