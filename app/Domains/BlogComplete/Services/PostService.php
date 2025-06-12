<?php

namespace App\Domains\BlogComplete\Services;

use App\Domains\BlogComplete\Models\Post;
use App\Domains\Shared\Services\BaseService;

class PostService extends BaseService
{
    public function __construct(private readonly Post $post)
    {
        $this->setModel($this->post);
    }

    // 👉 methods
    
}
