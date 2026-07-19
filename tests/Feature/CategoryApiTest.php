<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_system_and_owned_categories_but_not_another_users(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $system = Category::factory()->system()->create(['name' => 'Shelter']);
        $owned = Category::factory()->for($user)->create(['name' => 'Cooking']);
        Category::factory()->for($otherUser)->create(['name' => 'Private']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $system->id, 'name' => 'Shelter'])
            ->assertJsonFragment(['id' => $owned->id, 'name' => 'Cooking'])
            ->assertJsonMissing(['name' => 'Private']);
    }

    public function test_user_category_names_are_trimmed_and_case_insensitive_duplicates_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/categories', ['name' => '  Cooking  '])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Cooking');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/categories', ['name' => 'cooking'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_users_can_update_and_delete_only_their_categories(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $owned = Category::factory()->for($user)->create();
        $system = Category::factory()->system()->create();
        $other = Category::factory()->for($otherUser)->create();

        $this->actingAs($user, 'sanctum')->putJson("/api/v1/categories/{$owned->id}", ['name' => 'Updated'])
            ->assertOk()->assertJsonPath('data.name', 'Updated');
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/categories/{$owned->id}")->assertNoContent();
        $this->actingAs($user, 'sanctum')->putJson("/api/v1/categories/{$system->id}", ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/categories/{$system->id}")->assertForbidden();
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/categories/{$other->id}")->assertNotFound();
    }

    public function test_system_categories_are_seeded(): void
    {
        $this->seed();

        $this->assertDatabaseHas('categories', ['is_system' => true]);
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id');
        });
        DB::table('items')->insert(['category_id' => $category->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/categories/{$category->id}")
            ->assertStatus(409)
            ->assertJson(['code' => 'CATEGORY_IN_USE']);
    }

    public function test_unauthenticated_users_cannot_access_categories(): void
    {
        $this->getJson('/api/v1/categories')->assertUnauthorized();
        $this->postJson('/api/v1/categories', ['name' => 'Cooking'])->assertUnauthorized();
    }
}
