<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\SizeChart;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Tracking\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SizeChartAdminService extends BaseService
{
    public function __construct(
        private readonly SizeChart $sizeChart,
        private readonly AuditLogger $auditLogger,
    ) {
        $this->setModel($this->sizeChart);
    }

    public function index(array $options = [], ?\Closure $builderCallback = null)
    {
        if (empty($options['sort_by'])) {
            $options['sort_by'] = 'name';
            $options['sort_order'] = 'asc';
        }

        return parent::index($options, $builderCallback);
    }

    public function show(string $id)
    {
        return $this->findById($id);
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $data = $this->onlyFillable($data);
            /** @var SizeChart $model */
            $model = $this->getModel()->newQuery()->create($data);
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'size_charts.create',
                $model,
                null,
                $this->auditSnapshot($model),
                request()
            );

            return $model;
        });
    }

    public function update(array $data, string $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $record = $this->findById($id);
            $old = $this->auditSnapshot($record);
            $data = $this->onlyFillable($data);
            $record->update($data);
            $record->refresh();
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'size_charts.update',
                $record,
                $old,
                $this->auditSnapshot($record),
                request()
            );

            return $record;
        });
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $record = $this->findById($id);
            $old = $this->auditSnapshot($record);
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'size_charts.delete',
                $record,
                $old,
                null,
                request()
            );

            return $record->delete();
        });
    }

    private function onlyFillable(array $data): array
    {
        $keys = $this->getModel()->getFillable();

        return array_intersect_key($data, array_flip($keys));
    }

    private function auditSnapshot(Model $model): array
    {
        return $model->getAttributes();
    }
}
