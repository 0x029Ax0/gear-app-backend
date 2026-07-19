<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGearItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'price_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'is_owned' => ['sometimes', 'boolean', Rule::prohibitedIf(fn (): bool => $this->boolean('is_ordered'))],
            'is_ordered' => ['sometimes', 'boolean'],
        ];
    }
}
