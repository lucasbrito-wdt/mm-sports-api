<?php

namespace App\Domains\BlogComplete\Services;

use App\Domains\BlogComplete\Models\Comment;
use App\Domains\Shared\Services\BaseService;

class CommentService extends BaseService
{
    public function __construct(private readonly Comment $comment)
    {
        $this->setModel($this->comment);
    }

    // 👉 methods
    public function listarPost($options) {
		$data = \App\Domains\BlogComplete\Models\Post::query()->paginate($options['per_page'] ?? 15);
		return [
			'data' => $data->items(),
			'total' => $data->total(),
			'page' => $data->currentPage(),
		];
	}
}
