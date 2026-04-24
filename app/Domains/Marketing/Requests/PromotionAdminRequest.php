<?php

namespace App\Domains\Marketing\Requests;

use App\Domains\Marketing\Enums\PromotionType;
use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PromotionAdminRequest extends BaseFormRequest
{
    public function store(): array
    {
        return array_merge($this->promotionFieldRules(), [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'ulid', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'ulid', 'exists:product_variants,id'],
        ]);
    }

    public function update(): array
    {
        return array_merge($this->promotionFieldRules(allOptional: true), [
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'ulid', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'ulid', 'exists:product_variants,id'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
                $s = $this->input('starts_at');
                $e = $this->input('ends_at');
                if ($s && $e && strtotime((string) $e) < strtotime((string) $s)) {
                    $v->errors()->add('ends_at', 'A data final deve ser posterior à inicial.');
                }
            }
            $items = $this->input('items', []);
            if (! is_array($items)) {
                return;
            }
            foreach ($items as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $pid = $row['product_id'] ?? null;
                $vid = $row['product_variant_id'] ?? null;
                if (empty($pid) && empty($vid)) {
                    $v->errors()->add(
                        "items.$i",
                        'Cada item deve ter product_id ou product_variant_id.'
                    );
                }
            }
        });
    }

    private function promotionFieldRules(bool $allOptional = false): array
    {
        $s = $allOptional ? 'sometimes' : 'required';
        $dateEnd = $allOptional
            ? ['sometimes', 'date']
            : ['required', 'date', 'after:starts_at'];
        $dateStart = $allOptional ? ['sometimes', 'date'] : ['required', 'date'];

        return [
            'name' => [$s, 'string', 'max:255'],
            'type' => [$s, Rule::enum(PromotionType::class)],
            'value' => [$s, 'numeric', 'min:0'],
            'starts_at' => $dateStart,
            'ends_at' => $dateEnd,
            'is_active' => ['sometimes', 'boolean'],
            'min_order_total' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
