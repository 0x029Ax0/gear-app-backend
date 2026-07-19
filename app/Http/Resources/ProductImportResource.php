<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->source_url,
            'status' => $this->status,
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'result' => $this->result,
            'gear_item_id' => $this->gear_item_id,
            'quantity' => $this->quantity,
            'in_possession' => $this->in_possession,
            'ordered' => $this->ordered,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
