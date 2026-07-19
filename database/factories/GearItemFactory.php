<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\GearItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GearItem> */
class GearItemFactory extends Factory
{
    protected $model = GearItem::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'name' => fake()->words(2, true),
            'quantity' => 1,
            'weight_grams' => fake()->numberBetween(50, 5000),
            'price_minor' => fake()->numberBetween(100, 50000),
            'currency_code' => 'EUR',
            'in_possession' => false,
            'ordered' => false,
        ];
    }

    public function owned(): static
    {
        return $this->state(['in_possession' => true]);
    }

    public function ordered(): static
    {
        return $this->state(['ordered' => true]);
    }

    public function wishlist(): static
    {
        return $this->state(['in_possession' => false, 'ordered' => false]);
    }
}
