<?php

namespace App\Domains\Storage\Controllers;

use App\Domains\Catalog\Models\Product;
use App\Domains\Shared\Controller\BaseController;
use App\Domains\Storage\Requests\PresignUploadRequest;
use App\Domains\Storage\Services\R2PresignService;
use Illuminate\Support\Str;

class UploadPresignController extends BaseController
{
    public function __construct(private readonly R2PresignService $service)
    {
        $this->setACL('products', ['create' => ['__invoke']]);
        parent::__construct();
    }

    public function __invoke(PresignUploadRequest $request, Product $product)
    {
        $data = $request->validated();
        $key = sprintf('products/%s/%s.%s', $product->id, (string) Str::uuid(), $data['ext']);

        $result = $this->service->generatePutUrl(
            key: $key,
            contentType: $data['mime'],
            contentLength: (int) $data['size'],
        );

        return response()->json(['data' => $result]);
    }
}
