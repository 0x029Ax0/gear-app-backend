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
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'weight_grams' => ['required', 'integer', 'min:0', 'max:100000000'],
            'price_minor' => ['nullable', 'integer', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', Rule::requiredIf($this->input('price_minor') !== null)],
            'product_url' => ['nullable', 'url:http,https', 'max:2048'],
            'in_possession' => ['required', 'boolean'],
            'ordered' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'image_source_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'product_url', 'image_source_url'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
        if ($this->input('price_minor') === null) {
            $this->merge(['currency_code' => null]);
        }
    }
}
