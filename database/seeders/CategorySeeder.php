<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Shelter', 'Sleeping', 'Cooking', 'Clothing', 'Electronics', 'Other'] as $name) {
            Category::updateOrCreate(
                ['user_id' => null, 'normalized_name' => Category::normalizeName($name)],
                ['name' => $name, 'is_system' => true],
            );
        }
    }
}
