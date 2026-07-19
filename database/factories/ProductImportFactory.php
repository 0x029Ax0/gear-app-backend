<?php

namespace Database\Factories;

use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductImport> */
class ProductImportFactory extends Factory
{
    protected $model = ProductImport::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_url' => 'https://example.test/product',
            'status' => ProductImport::PENDING,
            'quantity' => 1,
            'expires_at' => now()->addHours(24),
        ];
    }
}
