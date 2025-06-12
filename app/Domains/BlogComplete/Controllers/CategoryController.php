<?php

namespace App\Domains\BlogComplete\Controllers;

use App\Domains\Shared\Controller\BaseController;
use App\Domains\BlogComplete\Services\CategoryService;
use App\Domains\BlogComplete\Requests\CategoryRequest;

class CategoryController extends BaseController
{
    public function __construct(private readonly CategoryService $service)
    {
        $this->setACL('blogcomplete', [
            'list' => ['blogcomplete.index'],
            'create' => ['blogcomplete.store'],
            'edit' => ['blogcomplete.update'],
            'delete' => ['blogcomplete.destroy']
        ]);
        parent::__construct();
        $this->setService($this->service);
        $this->setRequest('request', CategoryRequest::class);
    }

    // 👉 methods
}
