<?php

namespace App\Http\Requests;

class UpdateGearItemRequest extends StoreGearItemRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['name' => ['sometimes', 'string', 'max:200']]);
    }
}
