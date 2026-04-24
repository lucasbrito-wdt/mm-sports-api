<?php

namespace App\Domains\Marketing\Controllers;

use App\Domains\Marketing\Requests\BannerAdminRequest;
use App\Domains\Marketing\Services\BannerAdminService;
use App\Domains\Shared\Controller\BaseController;
use Illuminate\Http\Request;

class BannerAdminController extends BaseController
{
    public function __construct(
        private readonly BannerAdminService $bannerAdminService,
    ) {
        $this->setACL('banners', [
            'list' => ['index'],
            'read' => ['show'],
            'create' => ['store'],
            'edit' => ['update'],
            'delete' => ['destroy'],
        ]);
        parent::__construct();
        $this->setService($this->bannerAdminService);
        $this->setRequest('request', BannerAdminRequest::class);
    }

    public function store(Request $request)
    {
        $validated = $this->request($request)->validated();

        return response()->json($this->bannerAdminService->store($validated), 201);
    }

    public function update(Request $request, string $banner)
    {
        $validated = $this->request($request)->validated();

        return response()->json($this->bannerAdminService->update($validated, $banner));
    }

    public function destroy(string $id)
    {
        $this->bannerAdminService->destroy($id);

        return response()->noContent();
    }
}
