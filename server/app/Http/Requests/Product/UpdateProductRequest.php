<?php

namespace App\Http\Requests\Product;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'string', 'exists:categories,id'],
            'name'         => ['sometimes', 'string', 'max:150'],
            'description'  => ['sometimes', 'nullable', 'string', 'max:500'],
            'price'        => ['sometimes', 'numeric', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
            'image_url'    => ['sometimes', 'nullable', 'url', 'max:500'],
        ];
    }
}
