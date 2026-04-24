<?php

namespace App\Domains\Reviews\Controllers;

use App\Domains\Reviews\Requests\ModerateProductReviewRequest;
use App\Domains\Reviews\Services\ProductReviewAdminService;
use App\Domains\Shared\Controller\BaseController;
use Illuminate\Http\Request;

class ProductReviewAdminController extends BaseController
{
    public function __construct(
        private readonly ProductReviewAdminService $productReviewAdminService,
    ) {
        $this->setACL('product', [
            'reviews list' => ['index', 'show'],
            'reviews moderate' => ['update'],
        ]);
        parent::__construct();
        $this->setService($this->productReviewAdminService);
        $this->setRequest('request', ModerateProductReviewRequest::class);
    }

    public function update(Request $request, string $product_review)
    {
        $validated = $this->request($request)->validated();

        return response()->json(
            $this->productReviewAdminService->moderate($product_review, $validated)
        );
    }
}
