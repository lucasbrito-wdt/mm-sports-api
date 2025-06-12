<?php

namespace App\Domains\BlogComplete\Services;

use App\Domains\BlogComplete\Models\Tag;
use App\Domains\Shared\Services\BaseService;

class TagService extends BaseService
{
    public function __construct(private readonly Tag $tag)
    {
        $this->setModel($this->tag);
    }

    // 👉 methods
    
}
