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

    public function test_user_can_crud_owned_gear_items_and_cannot_access_another_users_items(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $item = GearItem::factory()->for($user)->create(['category_id' => $category->id]);
        $foreign = GearItem::factory()->for($other)->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/gear-items')->assertOk()
            ->assertJsonPath('data.0.id', $item->id);
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/gear-items/{$foreign->id}")->assertNotFound();
        $this->actingAs($user, 'sanctum')->putJson("/api/v1/gear-items/{$item->id}", ['name' => 'Updated pack'])
            ->assertOk()->assertJsonPath('data.name', 'Updated pack');
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/gear-items/{$item->id}")->assertNoContent();
        $this->assertSoftDeleted('gear_items', ['id' => $item->id]);
    }

    public function test_store_validates_positive_quantity_nonnegative_measurements_and_state(): void
    {
        $user = User::factory()->create();
        $payload = ['name' => 'Tent', 'quantity' => 2, 'weight_grams' => 1500, 'price_minor' => 12500, 'is_owned' => true];

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/gear-items', $payload)
            ->assertCreated()->assertJsonPath('data.total_weight_grams', 3000)
            ->assertJsonPath('data.total_price_minor', 25000)->assertJsonPath('data.status', 'owned');
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/gear-items', $payload + ['is_ordered' => true])
            ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/gear-items', ['name' => 'Bad', 'quantity' => 0, 'weight_grams' => -1, 'price_minor' => -1])
            ->assertUnprocessable();
    }

    public function test_index_supports_search_filters_sort_pagination_and_summary(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        GearItem::factory()->for($user)->create(['name' => 'Big tent', 'category_id' => $category->id, 'quantity' => 2, 'weight_grams' => 1000, 'price_minor' => 5000, 'is_owned' => true]);
        GearItem::factory()->for($user)->create(['name' => 'Small mug', 'quantity' => 1, 'weight_grams' => 200, 'price_minor' => 1000, 'is_owned' => true]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/gear-items?search=tent&category_id='.$category->id.'&sort=-weight_grams&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Big tent')
            ->assertJsonPath('meta.summary.quantity', 2)->assertJsonPath('meta.summary.weight_grams', 2000)
            ->assertJsonPath('meta.summary.price_minor', 10000);
    }

    public function test_unauthenticated_users_cannot_access_gear_items(): void
    {
        $this->getJson('/api/v1/gear-items')->assertUnauthorized();
        $this->postJson('/api/v1/gear-items', ['name' => 'Tent'])->assertUnauthorized();
    }
}
