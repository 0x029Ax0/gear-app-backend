<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GearItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GearItemApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_crud_gear_items_and_cannot_access_another_users_items(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $item = GearItem::factory()->for($user)->create(['category_id' => $category->id]);
        $otherCategory = Category::factory()->for($other)->create();
        $foreign = GearItem::factory()->for($other)->create(['category_id' => $otherCategory->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/gear-items')->assertOk()
            ->assertJsonPath('data.0.id', $item->id);
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/gear-items/{$foreign->id}")->assertNotFound();
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/gear-items/{$item->id}", ['name' => 'Updated pack'])
            ->assertOk()->assertJsonPath('data.name', 'Updated pack');
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/gear-items/{$item->id}")->assertNoContent();
        $this->assertSoftDeleted('gear_items', ['id' => $item->id]);
    }

    public function test_store_validates_required_fields_money_and_state(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $payload = [
            'category_id' => $category->id,
            'name' => 'Tent',
            'quantity' => 2,
            'weight_grams' => 1500,
            'price_minor' => 12500,
            'currency_code' => 'EUR',
            'in_possession' => true,
            'ordered' => false,
        ];

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/gear-items', $payload)
            ->assertCreated()->assertJsonPath('data.total_weight_grams', 3000)
            ->assertJsonPath('data.total_value_minor', 25000)->assertJsonPath('data.status', 'owned')
            ->assertJsonPath('data.currency_code', 'EUR')->assertJsonMissingPath('data.image_path');
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/gear-items', array_replace($payload, ['ordered' => true]))
            ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/gear-items', ['name' => 'Bad', 'quantity' => 0, 'weight_grams' => -1, 'price_minor' => -1])
            ->assertUnprocessable();
    }

    public function test_patch_revalidates_the_final_combined_status(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $item = GearItem::factory()->for($user)->create([
            'category_id' => $category->id,
            'ordered' => true,
            'in_possession' => false,
        ]);

        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/gear-items/{$item->id}", ['in_possession' => true])
            ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_index_supports_search_filters_sort_pagination_and_summary(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        GearItem::factory()->for($user)->create(['name' => 'Big tent', 'category_id' => $category->id, 'quantity' => 2, 'weight_grams' => 1000, 'price_minor' => 5000, 'in_possession' => true]);
        GearItem::factory()->for($user)->create(['name' => 'Small mug', 'category_id' => $category->id, 'quantity' => 1, 'weight_grams' => 200, 'price_minor' => 1000, 'in_possession' => true]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/gear-items?q=tent&category_id='.$category->id.'&sort=-weight_grams&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Big tent')
            ->assertJsonPath('meta.summary.total_quantity', 2)->assertJsonPath('meta.summary.total_weight_grams', 2000)
            ->assertJsonPath('meta.summary.total_value_minor', 10000);
    }

    public function test_unauthenticated_users_cannot_access_gear_items(): void
    {
        $this->getJson('/api/v1/gear-items')->assertUnauthorized();
        $this->postJson('/api/v1/gear-items', ['name' => 'Tent'])->assertUnauthorized();
    }
}
