<?php

namespace App\Domains\Reviews\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;

class StoreProductReviewRequest extends BaseFormRequest
{
    public function store(): array
    {
        return [
            'product_id' => ['required', 'ulid', 'exists:products,id'],
            'order_id' => ['nullable', 'ulid', 'exists:orders,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
