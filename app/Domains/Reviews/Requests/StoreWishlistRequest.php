<?php

namespace App\Domains\Reviews\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;

class StoreWishlistRequest extends BaseFormRequest
{
    public function store(): array
    {
        return [
            'product_variant_id' => ['required', 'ulid', 'exists:product_variants,id'],
        ];
    }
}
