<?php

namespace App\Domains\Marketing\Controllers;

use App\Domains\Marketing\Requests\CouponAdminRequest;
use App\Domains\Marketing\Services\CouponAdminService;
use App\Domains\Shared\Controller\BaseController;
use Illuminate\Http\Request;

class CouponAdminController extends BaseController
{
    public function __construct(
        private readonly CouponAdminService $service,
    ) {
        $this->setACL('coupons', [
            'list' => ['index'],
            'read' => ['show', 'metrics'],
            'create' => ['store'],
            'edit' => ['update'],
            'delete' => ['destroy'],
        ]);
        parent::__construct();
        $this->setService($this->service);
        $this->setRequest('request', CouponAdminRequest::class);
    }

    public function store(Request $request)
    {
        $validated = $this->request($request)->validated();

        return response()->json($this->service->store($validated), 201);
    }

    public function update(Request $request, string $coupon)
    {
        $validated = $this->request($request)->validated();

        return response()->json($this->service->update($validated, $coupon));
    }

    public function destroy(string $id)
    {
        $this->service->destroy($id);

        return response()->noContent();
    }

    public function metrics(Request $request, string $coupon)
    {
        $days = (int) $request->query('days', 30);
        $days = max(1, min($days, 365));

        return response()->json($this->service->metrics($coupon, $days));
    }
}
