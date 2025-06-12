<?php

namespace App\Domains\BlogComplete\Controllers;

use App\Domains\Shared\Controller\BaseController;
use App\Domains\BlogComplete\Services\CommentService;
use App\Domains\BlogComplete\Requests\CommentRequest;

class CommentController extends BaseController
{
    public function __construct(private readonly CommentService $service)
    {
        $this->setACL('blogcomplete', [
            'list' => ['blogcomplete.index'],
            'create' => ['blogcomplete.store'],
            'edit'=> ['blogcomplete.update'],
            'delete' => ['blogcomplete.destroy']
        ]);
        parent::__construct();
        $this->setService($this->service);
        $this->setRequest('request', CommentRequest::class);
    }

    // 👉 methods
    public function listarPost(Request $request) {
		$options = $request->all();
		return $this->service->listarPost($options);
	}
}
