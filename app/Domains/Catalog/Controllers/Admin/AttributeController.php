<?php

namespace App\Domains\Catalog\Controllers\Admin;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Requests\Admin\AttributeRequest;
use App\Domains\Catalog\Services\AttributeAdminService;
use App\Domains\Shared\Traits\Dependencies;
use App\Domains\Shared\Traits\HasACL;
use App\Domains\Shared\Traits\ResolvesFormRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AttributeController extends Controller
{
    use Dependencies, HasACL, ResolvesFormRequest;

    public function __construct(
        private readonly AttributeAdminService $attributeAdminService,
    ) {
        $this->setACL('attributes', [
            'list' => ['index'],
            'create' => ['store'],
            'edit' => ['update'],
            'delete' => ['destroy'],
        ]);
        $this->setRequest('request', AttributeRequest::class);
        $this->bootACL();
    }

    public function index(Request $request): JsonResponse
    {
        unset($request);

        return response()->json([
            'data' => Attribute::query()->with('values')->orderBy('display_order')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->request($request)->validated();

        return response()->json([
            'data' => $this->attributeAdminService->create($validated),
        ], 201);
    }

    public function update(Request $request, Attribute $attribute): JsonResponse
    {
        $validated = $this->request($request)->validated();

        return response()->json([
            'data' => $this->attributeAdminService->updateModel($attribute, $validated),
        ]);
    }

    public function destroy(Attribute $attribute): Response
    {
        $this->attributeAdminService->delete($attribute);

        return response()->noContent();
    }
}
