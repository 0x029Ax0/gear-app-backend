<?php

namespace App\Models;

use Database\Factories\GearItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'category_id', 'name', 'description', 'quantity', 'weight_grams', 'price_minor', 'currency', 'is_owned', 'is_ordered', 'image_path'])]
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
            'is_owned' => 'boolean',
            'is_ordered' => 'boolean',
        ];
    }

    protected function status(): Attribute
    {
        return Attribute::get(fn (): string => match (true) {
            $this->is_owned => 'owned',
            $this->is_ordered => 'ordered',
            default => 'planned',
        });
    }

    protected function totalWeightGrams(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->weight_grams === null ? null : $this->weight_grams * $this->quantity);
    }

    protected function totalPriceMinor(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->price_minor === null ? null : $this->price_minor * $this->quantity);
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
