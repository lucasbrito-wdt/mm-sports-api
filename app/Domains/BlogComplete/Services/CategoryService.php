<?php

namespace App\Domains\BlogComplete\Services;

use App\Domains\BlogComplete\Models\Category;
use App\Domains\Shared\Services\BaseService;

class CategoryService extends BaseService
{
    public function __construct(private readonly Category $category)
    {
        $this->setModel($this->category);
    }

    // 👉 methods
    
}
