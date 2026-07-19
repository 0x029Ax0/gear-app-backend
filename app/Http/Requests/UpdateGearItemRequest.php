<?php

namespace App\Http\Requests;

class UpdateGearItemRequest extends StoreGearItemRequest
{
    public function rules(): array
    {
        return collect(parent::rules())->map(fn (array $rules, string $field): array => $field === 'category_id' || $field === 'name' || $field === 'quantity' || $field === 'weight_grams' || $field === 'in_possession' || $field === 'ordered'
            ? array_values(array_filter($rules, fn (string $rule): bool => $rule !== 'required'))
            : $rules)->all();
    }
}
