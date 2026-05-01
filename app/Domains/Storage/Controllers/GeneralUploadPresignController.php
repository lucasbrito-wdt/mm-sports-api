<?php

namespace App\Domains\Storage\Controllers;

use App\Domains\Shared\Controller\BaseController;
use App\Domains\Storage\Requests\GeneralPresignUploadRequest;
use App\Domains\Storage\Services\R2PresignService;
use Illuminate\Support\Str;

class GeneralUploadPresignController extends BaseController
{
    public function __construct(private readonly R2PresignService $service)
    {
        parent::__construct();
    }

    public function __invoke(GeneralPresignUploadRequest $request)
    {
        $data = $request->validated();
        $key = sprintf('%s/%s.%s', $data['context'], (string) Str::uuid(), $data['ext']);

        $result = $this->service->generatePutUrl(
            key: $key,
            contentType: $data['mime'],
            contentLength: (int) $data['size'],
        );

        return response()->json(['data' => $result]);
    }
}
