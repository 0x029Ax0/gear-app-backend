<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GearItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'quantity' => $this->quantity,
            'weight_grams' => $this->weight_grams,
            'total_weight_grams' => $this->total_weight_grams,
            'price_minor' => $this->price_minor,
            'total_price_minor' => $this->total_price_minor,
            'currency' => $this->currency,
            'is_owned' => $this->is_owned,
            'is_ordered' => $this->is_ordered,
            'status' => $this->status,
            'image_path' => $this->image_path,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
