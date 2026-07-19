<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GearItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_user_can_upload_replace_and_delete_a_gear_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $item = GearItem::factory()->for($user)->create(['category_id' => $category->id]);

        $first = $this->actingAs($user, 'sanctum')->post("/api/v1/gear-items/{$item->id}/image", [
            'image' => $this->validPng('first.png'),
        ]);
        $first->assertOk()->assertJsonPath('data.image_url', fn (string $url): bool => str_contains($url, 'gear-images/'));
        $firstPath = GearItem::findOrFail($item->id)->image_path;
        Storage::disk('public')->assertExists($firstPath);

        $this->actingAs($user, 'sanctum')->post("/api/v1/gear-items/{$item->id}/image", [
            'image' => $this->validPng('second.png'),
        ])->assertOk();
        $secondPath = GearItem::findOrFail($item->id)->image_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);

        $this->actingAs($user, 'sanctum')->delete("/api/v1/gear-items/{$item->id}/image")
            ->assertNoContent();
        Storage::disk('public')->assertMissing($secondPath);
        $this->assertNull(GearItem::findOrFail($item->id)->image_path);
    }

    public function test_image_upload_rejects_invalid_content_and_foreign_items(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $otherCategory = Category::factory()->for($other)->create();
        $item = GearItem::factory()->for($user)->create(['category_id' => $category->id]);
        $foreign = GearItem::factory()->for($other)->create(['category_id' => $otherCategory->id]);

        $this->actingAs($user, 'sanctum')->post("/api/v1/gear-items/{$item->id}/image", [
            'image' => UploadedFile::fake()->createWithContent('not-image.txt', 'not an image'),
        ])->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');

        $this->actingAs($user, 'sanctum')->post("/api/v1/gear-items/{$foreign->id}/image", [
            'image' => $this->validPng(),
        ])->assertNotFound();
    }

    private function validPng(string $name = 'image.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));
    }
}
