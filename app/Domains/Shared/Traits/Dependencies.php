<?php

namespace App\Domains\Shared\Traits;

use App\Domains\Shared\Models\BaseModel;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Model;

trait Dependencies
{
    /* @var BaseService $service */
    private BaseService $service;

    /* @var Model $model */
    private Model $model;

    /* @var array $request */
    private array $request = [];

    private array $ACL = [];

    public function __construct()
    {
        $this->service = new BaseService;
        $this->request = [];
        $this->model = new BaseModel;
        $this->ACL = [];
    }

    /**
     * Get Service Layer
     */
    public function getService(): BaseService
    {
        return $this->service;
    }

    /**
     * Set Service Layer
     */
    public function setService(BaseService $service): void
    {
        $this->service = $service;
    }

    /**
     * Get HTTP Requests
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * Set Request represents an HTTP request.
     *
     * example: $this->setRequest('request', ProductRequest::class)
     */
    public function setRequest(string $requestName, string $request): void
    {
        $this->request = [
            'requestName' => $requestName,
            'requestClass' => $request,
        ];
    }

    /**
     * Get Eloquent Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Set Eloquent Model
     */
    public function setModel(Model $model): void
    {
        $this->model = $model;
    }

    public function getACL(): array
    {
        return $this->ACL;
    }

    public function setACL($subject, string|array $roleOrActionsPermission): void
    {
        $rules = is_array($roleOrActionsPermission) ? [] : $roleOrActionsPermission;

        if (is_array($roleOrActionsPermission)) {
            foreach ($roleOrActionsPermission as $method => $action) {
                foreach ($action as $actionItem) {
                    $rules[$actionItem][] = implode(' ', [$subject, $method]);
                }
            }
        }
        $this->ACL = [
            'subject' => $subject,
            'rules' => $rules,
        ];
    }
}
