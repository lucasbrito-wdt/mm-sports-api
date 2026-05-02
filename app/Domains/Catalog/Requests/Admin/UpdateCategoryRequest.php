<?php

namespace App\Domains\Catalog\Requests\Admin;

use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends BaseFormRequest
{
    public function update(): array
    {
        $categoryId = (string) ($this->route('category') ?? $this->route('id'));
        $parentIdRules = ['nullable', 'ulid', 'exists:categories,id'];
        if ($categoryId !== '') {
            $parentIdRules[] = Rule::notIn([$categoryId]);
        }

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($categoryId, 'id')],
            'parent_id' => $parentIdRules,
            'is_active' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
