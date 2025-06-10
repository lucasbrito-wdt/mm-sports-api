<?php

namespace App\Domains\Shared\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Concerns\ValidatesAttributes;

class BaseFormRequest extends FormRequest
{
    use ValidatesAttributes;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return match ($this->method()) {
            'POST' => array_merge($this->base(), $this->store()),
            'PUT', 'PATCH' => array_merge($this->base(), $this->update()),
            'DELETE' => $this->destroy(),
            default => $this->view()
        };
    }

    public function base(): array
    {
        return [
            //
        ];
    }

    /**
     * Get the validation rules that apply to the get request.
     *
     * @return array
     */
    public function view(): array
    {
        return [
            //
        ];
    }

    /**
     * Get the validation rules that apply to the post request.
     *
     * @return array
     */
    public function store(): array
    {
        return [
            //
        ];
    }

    /**
     * Get the validation rules that apply to the put/patch request.
     *
     * @return array
     */
    public function update(): array
    {
        return [
            //
        ];
    }

    /**
     * Get the validation rules that apply to the delete request.
     *
     * @return array
     */
    public function destroy(): array
    {
        return [
            //
        ];
    }

    public function messages()
    {
        return [];
    }
}
