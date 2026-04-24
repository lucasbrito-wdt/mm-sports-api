<?php

namespace App\Domains\Catalog\Controllers\Admin;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Requests\Admin\AttributeValueRequest;
use App\Domains\Catalog\Services\AttributeValueAdminService;
use App\Domains\Shared\Traits\Dependencies;
use App\Domains\Shared\Traits\HasACL;
use App\Domains\Shared\Traits\ResolvesFormRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AttributeValueController extends Controller
{
    use Dependencies, HasACL, ResolvesFormRequest;

    public function __construct(
        private readonly AttributeValueAdminService $attributeValueAdminService,
    ) {
        $this->setACL('attributes', [
            'values create' => ['store'],
            'values edit' => ['update'],
            'values delete' => ['destroy'],
        ]);
        $this->setRequest('request', AttributeValueRequest::class);
        $this->bootACL();
    }

    public function store(Request $request, Attribute $attribute): JsonResponse
    {
        $validated = $this->request($request)->validated();

        return response()->json([
            'data' => $this->attributeValueAdminService->create($attribute, $validated),
        ], 201);
    }

    public function update(Request $request, AttributeValue $value): JsonResponse
    {
        $validated = $this->request($request)->validated();

        return response()->json([
            'data' => $this->attributeValueAdminService->updateModel($value, $validated),
        ]);
    }

    public function destroy(AttributeValue $value): Response
    {
        $this->attributeValueAdminService->delete($value);

        return response()->noContent();
    }
}
