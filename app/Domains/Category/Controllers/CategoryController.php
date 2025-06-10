<?php

namespace App\Domains\Category\Controllers;

use App\Domains\Shared\Controller\BaseController;
use App\Domains\Category\Services\CategoryService;
use App\Domains\Category\Requests\CategoryRequest;

class CategoryController extends BaseController
{
    public function __construct(private readonly CategoryService $service)
    {
        $this->setACL('category', [
            'list' => ['category.index'],
            'create' => ['category.store'],
            'edit'=> ['category.update'],
            'delete' => ['category.destroy']
        ]);
        parent::__construct();
        $this->setService($this->service);
        $this->setRequest('request', CategoryRequest::class);
    }

    // 👉 methods
    
}
