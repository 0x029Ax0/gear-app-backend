<?php

namespace App\Models;

use Database\Factories\ProductImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'gear_item_id', 'category_id', 'source_url', 'status', 'failure_code', 'failure_message', 'result', 'quantity', 'in_possession', 'ordered', 'started_at', 'completed_at', 'expires_at'])]
class ProductImport extends Model
{
    /** @use HasFactory<ProductImportFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'quantity' => 'integer',
            'in_possession' => 'boolean',
            'ordered' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gearItem(): BelongsTo
    {
        return $this->belongsTo(GearItem::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
