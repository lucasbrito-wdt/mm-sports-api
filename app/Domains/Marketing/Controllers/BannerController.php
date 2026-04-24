<?php

namespace App\Domains\Marketing\Controllers;

use App\Domains\Marketing\Services\BannerListService;
use App\Domains\Shared\Controller\BaseController;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends BaseController
{
    public function __construct(
        private readonly BannerListService $bannerListService,
    ) {
        parent::__construct();
    }

    public function index(Request $request, ?Closure $builderCallback = null): JsonResponse
    {
        return response()->json($this->bannerListService->listActiveForPublic());
    }
}
