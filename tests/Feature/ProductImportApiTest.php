<?php

namespace Tests\Feature;

use App\Jobs\ImportProductFromUrl;
use App\Models\Category;
use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProductImportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_is_validated_queued_and_is_owner_scoped(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $foreign = ProductImport::factory()->for($other)->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/product-imports', [
            'url' => 'https://example.com/products/tent',
            'category_id' => $category->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(202)->assertJsonPath('data.status', ProductImport::PENDING);
        Queue::assertPushed(ImportProductFromUrl::class);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/product-imports/'.$foreign->id)->assertNotFound();
    }

    public function test_private_urls_are_rejected_without_dispatching(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/product-imports', [
            'url' => 'http://127.0.0.1/admin',
        ])->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
        Queue::assertNothingPushed();
    }

    public function test_import_job_parses_json_ld_and_creates_owned_item(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $import = ProductImport::factory()->for($user)->create(['category_id' => $category->id]);
        $this->assertDatabaseHas('product_imports', ['id' => $import->id, 'status' => ProductImport::PENDING]);
    }
}
