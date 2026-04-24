<?php

namespace App\Domains\Commerce\Controllers;

use App\Domains\Commerce\Requests\CheckoutQuoteRequest;
use App\Domains\Commerce\Services\CheckoutQuoteService;
use App\Domains\Shared\Controller\BaseController;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class CheckoutQuoteController extends BaseController
{
    public function __construct(
        private readonly CheckoutQuoteService $checkoutQuoteService,
    ) {
        parent::__construct();
    }

    public function quote(CheckoutQuoteRequest $request): JsonResponse
    {
        try {
            $out = $this->checkoutQuoteService->quoteForUser($request->user()->id, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($out);
    }
}
