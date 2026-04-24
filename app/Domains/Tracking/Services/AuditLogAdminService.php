<?php

namespace App\Domains\Tracking\Services;

use App\Domains\Shared\Services\BaseService;
use App\Domains\Tracking\Models\AuditLog;

class AuditLogAdminService extends BaseService
{
    public function __construct(
        private readonly AuditLog $auditLog,
    ) {
        $this->setModel($this->auditLog);
    }

    public function index(array $options = [], ?\Closure $builderCallback = null)
    {
        if (empty($options['sort_by'])) {
            $options['sort_by'] = 'created_at';
            $options['sort_order'] = 'desc';
        }

        return parent::index($options, function ($query) use ($options, $builderCallback): void {
            if (! empty($options['date_from'])) {
                $query->where('created_at', '>=', $options['date_from']);
            }
            if (! empty($options['date_to'])) {
                $query->where('created_at', '<=', $options['date_to']);
            }
            if ($builderCallback !== null) {
                $builderCallback($query);
            }
        });
    }

    public function show(string $id)
    {
        return $this->findById($id);
    }
}
