<?php

namespace App\Domains\Tracking\Services;

use App\Domains\Tracking\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    public function __construct(
        private readonly AuditLog $auditLog,
    ) {}

    public function log(
        ?string $actorId,
        string $action,
        Model $model,
        ?array $old,
        ?array $new,
        ?Request $request = null,
    ): void {
        $this->auditLog->newQuery()->create([
            'id' => (string) Str::ulid(),
            'actor_user_id' => $actorId,
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => (string) $model->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
