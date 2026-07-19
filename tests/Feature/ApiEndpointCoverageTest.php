<?php

use App\Jobs\ImportProductFromUrl;
use App\Models\Category;
use App\Models\GearItem;
use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

it('exposes the category show endpoint for visible categories', function (): void {
    $user = User::factory()->create();
    $system = Category::factory()->system()->create();
    $owned = Category::factory()->for($user)->create();

    $this->actingAs($user, 'sanctum')->getJson("/api/v1/categories/{$system->id}")
        ->assertOk()->assertJsonPath('data.id', $system->id);
    $this->actingAs($user, 'sanctum')->getJson("/api/v1/categories/{$owned->id}")
        ->assertOk()->assertJsonPath('data.id', $owned->id);
});

it('rejects category updates that collide with a system or owned category', function (): void {
    $user = User::factory()->create();
    $owned = Category::factory()->for($user)->create(['name' => 'Cooking']);
    Category::factory()->system()->create(['name' => 'Shelter']);

    $this->actingAs($user, 'sanctum')->patchJson("/api/v1/categories/{$owned->id}", ['name' => 'Shelter'])
        ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
});

it('rejects attempts to create a category with a system category name', function (): void {
    $user = User::factory()->create();
    Category::factory()->system()->create(['name' => 'Shelter']);

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/categories', ['name' => ' shelter '])
        ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
});

it('creates and updates gear items with visible system categories', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->system()->create();

    $create = $this->actingAs($user, 'sanctum')->postJson('/api/v1/gear-items', [
        'category_id' => $category->id,
        'name' => 'Rain shell',
        'quantity' => 1,
        'weight_grams' => 300,
        'price_minor' => null,
        'in_possession' => false,
        'ordered' => true,
    ]);

    $create->assertCreated()->assertJsonPath('data.category.id', $category->id);
    $id = $create->json('data.id');
    $this->actingAs($user, 'sanctum')->patchJson("/api/v1/gear-items/{$id}", [
        'category_id' => $category->id,
        'notes' => 'Packable',
    ])->assertOk()->assertJsonPath('data.notes', 'Packable');
});

it('rejects inaccessible gear categories and invalid list sorting', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $category = Category::factory()->for($other)->create();

    $payload = [
        'category_id' => $category->id,
        'name' => 'Tent',
        'quantity' => 1,
        'weight_grams' => 100,
        'in_possession' => false,
        'ordered' => false,
    ];

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/gear-items', $payload)
        ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/gear-items?sort=invalid')
        ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
});

it('filters gear items by possession and ordered state and summarizes currencies separately', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    GearItem::factory()->for($user)->create(['category_id' => $category->id, 'in_possession' => true, 'ordered' => false, 'currency_code' => 'EUR', 'price_minor' => 100]);
    GearItem::factory()->for($user)->create(['category_id' => $category->id, 'in_possession' => false, 'ordered' => true, 'currency_code' => 'USD', 'price_minor' => 200]);

    $filtered = $this->actingAs($user, 'sanctum')->getJson('/api/v1/gear-items?in_possession=1&ordered=0');
    $filtered->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.summary.total_value_minor', 100);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/gear-items');
    $response->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.summary.values_by_currency.EUR', 100)
        ->assertJsonPath('meta.summary.values_by_currency.USD', 200)
        ->assertJsonMissingPath('meta.summary.total_value_minor');
});

it('deletes a gear image endpoint for an owned item and rejects foreign image access', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $otherCategory = Category::factory()->for($other)->create();
    $item = GearItem::factory()->for($user)->create(['category_id' => $category->id, 'image_path' => 'gear-images/old.png']);
    $foreign = GearItem::factory()->for($other)->create(['category_id' => $otherCategory->id, 'image_path' => 'gear-images/foreign.png']);

    $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/gear-items/{$item->id}/image")->assertNoContent();
    $this->assertDatabaseHas('gear_items', ['id' => $item->id, 'image_path' => null]);
    $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/gear-items/{$foreign->id}/image")->assertNotFound();
});

it('lists, shows, and deletes only owned product imports', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $owned = ProductImport::factory()->for($user)->create();
    $foreign = ProductImport::factory()->for($other)->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/product-imports')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $owned->id);
    $this->actingAs($user, 'sanctum')->getJson("/api/v1/product-imports/{$owned->id}")
        ->assertOk()->assertJsonPath('data.id', $owned->id);
    $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/product-imports/{$owned->id}")->assertNoContent();
    $this->actingAs($user, 'sanctum')->getJson("/api/v1/product-imports/{$foreign->id}")->assertNotFound();
});

it('rejects invalid product import state and inaccessible category', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $category = Category::factory()->for($other)->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/product-imports', [
        'url' => 'https://1.1.1.1/product',
        'category_id' => $category->id,
        'in_possession' => true,
        'ordered' => true,
    ])->assertUnprocessable();
    Queue::assertNothingPushed();
});

it('enforces the pending product import limit', function (): void {
    Queue::fake();
    config(['product_imports.max_pending_per_user' => 1]);
    $user = User::factory()->create();
    ProductImport::factory()->for($user)->create(['status' => ProductImport::PENDING]);

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/product-imports', ['url' => 'https://1.1.1.1/product'])
        ->assertUnprocessable();
    Queue::assertNothingPushed();
});

it('rate limits product imports', function (): void {
    Queue::fake();
    config(['product_imports.rate_limit_per_minute' => 1]);
    $user = User::factory()->create();
    RateLimiter::clear('product-import:'.$user->id);

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/product-imports', ['url' => 'https://1.1.1.1/one'])
        ->assertStatus(202);
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/product-imports', ['url' => 'https://1.1.1.1/two'])
        ->assertTooManyRequests();
    Queue::assertPushedTimes(ImportProductFromUrl::class, 1);
});

it('protects every authenticated resource endpoint', function (): void {
    $this->getJson('/api/v1/categories')->assertUnauthorized();
    $this->getJson('/api/v1/gear-items')->assertUnauthorized();
    $this->postJson('/api/v1/gear-items/1/image')->assertUnauthorized();
    $this->deleteJson('/api/v1/gear-items/1/image')->assertUnauthorized();
    $this->getJson('/api/v1/product-imports')->assertUnauthorized();
    $this->postJson('/api/v1/product-imports')->assertUnauthorized();
});
