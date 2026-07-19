<?php

namespace Database\Factories;

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
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'quantity' => 1,
            'weight_grams' => fake()->numberBetween(50, 5000),
            'price_minor' => fake()->numberBetween(100, 50000),
            'currency' => 'USD',
            'is_owned' => false,
            'is_ordered' => false,
        ];
    }

    public function owned(): static
    {
        return $this->state(['is_owned' => true]);
    }

    public function ordered(): static
    {
        return $this->state(['is_ordered' => true]);
    }
}
