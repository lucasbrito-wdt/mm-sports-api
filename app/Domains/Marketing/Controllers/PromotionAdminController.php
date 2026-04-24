<?php

namespace App\Domains\Marketing\Controllers;

use App\Domains\Marketing\Requests\PromotionAdminRequest;
use App\Domains\Marketing\Services\PromotionAdminService;
use App\Domains\Shared\Controller\BaseController;
use Illuminate\Http\Request;

class PromotionAdminController extends BaseController
{
    public function __construct(
        private readonly PromotionAdminService $promotionAdminService,
    ) {
        $this->setACL('promotions', [
            'list' => ['index'],
            'read' => ['show'],
            'create' => ['store'],
            'edit' => ['update'],
            'delete' => ['destroy'],
        ]);
        parent::__construct();
        $this->setService($this->promotionAdminService);
        $this->setRequest('request', PromotionAdminRequest::class);
    }

    public function store(Request $request)
    {
        $validated = $this->request($request)->validated();

        return response()->json($this->promotionAdminService->store($validated), 201);
    }

    public function update(Request $request, string $promotion)
    {
        $validated = $this->request($request)->validated();

        return response()->json($this->promotionAdminService->update($validated, $promotion));
    }

    public function destroy(string $id)
    {
        $this->promotionAdminService->destroy($id);

        return response()->noContent();
    }
}
