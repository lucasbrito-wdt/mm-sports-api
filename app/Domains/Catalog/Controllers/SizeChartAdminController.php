<?php

namespace App\Domains\Catalog\Controllers;

use App\Domains\Catalog\Requests\SizeChartAdminRequest;
use App\Domains\Catalog\Services\SizeChartAdminService;
use App\Domains\Shared\Controller\BaseController;
use Illuminate\Http\Request;

class SizeChartAdminController extends BaseController
{
    public function __construct(
        private readonly SizeChartAdminService $sizeChartAdminService,
    ) {
        $this->setACL('size', [
            'charts list' => ['index'],
            'charts read' => ['show'],
            'charts create' => ['store'],
            'charts edit' => ['update'],
            'charts delete' => ['destroy'],
        ]);
        parent::__construct();
        $this->setService($this->sizeChartAdminService);
        $this->setRequest('request', SizeChartAdminRequest::class);
    }

    public function store(Request $request)
    {
        $validated = $this->request($request)->validated();

        return response()->json($this->sizeChartAdminService->store($validated), 201);
    }

    public function update(Request $request, string $size_chart)
    {
        $validated = $this->request($request)->validated();

        return response()->json($this->sizeChartAdminService->update($validated, $size_chart));
    }

    public function destroy(string $size_chart)
    {
        $this->sizeChartAdminService->destroy($size_chart);

        return response()->noContent();
    }
}
