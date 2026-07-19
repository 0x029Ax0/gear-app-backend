<?php

namespace App\Models;

use Database\Factories\GearItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'category_id', 'name', 'quantity', 'weight_grams', 'price_minor', 'currency_code', 'product_url', 'image_path', 'image_source_url', 'in_possession', 'ordered', 'notes', 'imported_at'])]
class GearItem extends Model
{
    /** @use HasFactory<GearItemFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'weight_grams' => 'integer',
            'price_minor' => 'integer',
            'in_possession' => 'boolean',
            'ordered' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    protected function status(): Attribute
    {
        return Attribute::get(fn (): string => match (true) {
            $this->in_possession => 'owned',
            $this->ordered => 'ordered',
            default => 'wishlist',
        });
    }

    protected function totalWeightGrams(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->weight_grams === null ? null : $this->weight_grams * $this->quantity);
    }

    protected function totalValueMinor(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->price_minor === null ? null : $this->price_minor * $this->quantity);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image_path === null ? null : Storage::url($this->image_path));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
