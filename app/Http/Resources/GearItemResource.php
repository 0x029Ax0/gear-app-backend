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
            'category' => new CategoryResource($this->whenLoaded('category')),
            'quantity' => $this->quantity,
            'weight_grams' => $this->weight_grams,
            'total_weight_grams' => $this->total_weight_grams,
            'price_minor' => $this->price_minor,
            'total_value_minor' => $this->total_value_minor,
            'currency_code' => $this->currency_code,
            'product_url' => $this->product_url,
            'image_url' => $this->image_url,
            'image_source_url' => $this->image_source_url,
            'in_possession' => $this->in_possession,
            'ordered' => $this->ordered,
            'status' => $this->status,
            'notes' => $this->notes,
            'imported_at' => $this->imported_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
