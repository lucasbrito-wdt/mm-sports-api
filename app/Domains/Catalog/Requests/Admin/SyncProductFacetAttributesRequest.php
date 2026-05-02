<?php

namespace App\Domains\Catalog\Requests\Admin;

use App\Domains\Shared\Requests\BaseFormRequest;

class SyncProductFacetAttributesRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'value_ids'   => ['present', 'array'],
            'value_ids.*' => ['string', 'exists:attribute_values,id'],
        ];
    }

    public function store(): array
    {
        return $this->base();
    }

    public function update(): array
    {
        return $this->base();
    }
}
