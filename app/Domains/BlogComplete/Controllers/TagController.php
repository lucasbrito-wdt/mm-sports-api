<?php

namespace App\Domains\BlogComplete\Controllers;

use App\Domains\Shared\Controller\BaseController;
use App\Domains\BlogComplete\Services\TagService;
use App\Domains\BlogComplete\Requests\TagRequest;

class TagController extends BaseController
{
    public function __construct(private readonly TagService $service)
    {
        $this->setACL('blogcomplete', [
            'list' => ['blogcomplete.index'],
            'create' => ['blogcomplete.store'],
            'edit'=> ['blogcomplete.update'],
            'delete' => ['blogcomplete.destroy']
        ]);
        parent::__construct();
        $this->setService($this->service);
        $this->setRequest('request', TagRequest::class);
    }

    // 👉 methods
    
}
