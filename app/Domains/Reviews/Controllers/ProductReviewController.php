<?php

namespace App\Domains\Reviews\Controllers;

use App\Domains\Reviews\Requests\StoreProductReviewRequest;
use App\Domains\Reviews\Services\ProductReviewService;
use App\Domains\Shared\Controller\BaseController;
use Illuminate\Http\JsonResponse;

class ProductReviewController extends BaseController
{
    public function __construct(
        private readonly ProductReviewService $productReviewService,
    ) {
        parent::__construct();
    }

    public function createReview(StoreProductReviewRequest $request): JsonResponse
    {
        $user = $request->user();
        $review = $this->productReviewService->createForUser($user, $request->validated());

        return response()->json(['data' => [
            'id' => (string) $review->id,
            'moderation_status' => $review->moderation_status->value,
        ]], 201);
    }
}
