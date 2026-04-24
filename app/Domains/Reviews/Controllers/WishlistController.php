<?php

namespace App\Domains\Reviews\Controllers;

use App\Domains\Reviews\Requests\StoreWishlistRequest;
use App\Domains\Reviews\Services\WishlistService;
use App\Domains\Shared\Controller\BaseController;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends BaseController
{
    public function __construct(
        private readonly WishlistService $wishlistService,
    ) {
        parent::__construct();
    }

    public function index(Request $request, ?Closure $builderCallback = null): JsonResponse
    {
        $user = $request->user();

        return response()->json($this->wishlistService->listForUser($user->id));
    }

    public function addToWishlist(StoreWishlistRequest $request): JsonResponse
    {
        $user = $request->user();
        $item = $this->wishlistService->add($user->id, $request->validated('product_variant_id'), $request);

        return response()->json(['data' => ['id' => (string) $item->id]], 201);
    }

    public function removeFromWishlist(Request $request, string $product_variant_id): JsonResponse
    {
        $this->wishlistService->remove($request->user()->id, $product_variant_id, $request);

        return response()->json(['ok' => true]);
    }
}
