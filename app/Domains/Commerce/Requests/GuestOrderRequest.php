<?php

namespace App\Domains\Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'billing_type' => ['required', 'string', 'in:PIX,CREDIT_CARD,BOLETO'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['required', 'email', 'max:255'],
            'customer.cpf' => ['required', 'string', 'min:11', 'max:14'],
            'customer.phone' => ['required', 'string', 'min:10', 'max:15'],
            'address' => ['required', 'array'],
            'address.postal_code' => ['required', 'string', 'min:8', 'max:9'],
            'address.street' => ['required', 'string', 'max:255'],
            'address.number' => ['required', 'string', 'max:20'],
            'address.complement' => ['nullable', 'string', 'max:100'],
            'address.city' => ['required', 'string', 'max:100'],
            'address.state' => ['required', 'string', 'size:2'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];

        if ($this->input('billing_type') === 'CREDIT_CARD') {
            $rules['credit_card'] = ['required', 'array'];
            $rules['credit_card.holder_name'] = ['required', 'string', 'max:255'];
            $rules['credit_card.number'] = ['required', 'string', 'min:13', 'max:19'];
            $rules['credit_card.expiry_month'] = ['required', 'string', 'size:2'];
            $rules['credit_card.expiry_year'] = ['required', 'string', 'size:4'];
            $rules['credit_card.ccv'] = ['required', 'string', 'min:3', 'max:4'];
            $rules['credit_card.holder_cpf'] = ['required', 'string', 'min:11', 'max:14'];
            $rules['credit_card.holder_phone'] = ['required', 'string', 'min:10', 'max:15'];
            $rules['credit_card.holder_postal_code'] = ['required', 'string', 'min:8', 'max:9'];
            $rules['credit_card.holder_address_number'] = ['required', 'string', 'max:20'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'billing_type.in' => 'Método de pagamento inválido.',
            'items.min' => 'O carrinho está vazio.',
            'credit_card.required' => 'Os dados do cartão são obrigatórios.',
        ];
    }
}
