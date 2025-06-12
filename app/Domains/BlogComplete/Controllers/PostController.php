<?php

namespace App\Domains\BlogComplete\Controllers;

use App\Domains\Shared\Controller\BaseController;
use App\Domains\BlogComplete\Services\PostService;
use App\Domains\BlogComplete\Requests\PostRequest;

class PostController extends BaseController
{
    public function __construct(private readonly PostService $service)
    {
        $this->setACL('blogcomplete', [
            'list' => ['blogcomplete.index'],
            'create' => ['blogcomplete.store'],
            'edit'=> ['blogcomplete.update'],
            'delete' => ['blogcomplete.destroy']
        ]);
        parent::__construct();
        $this->setService($this->service);
        $this->setRequest('request', PostRequest::class);
    }

    // 👉 methods
    
}
