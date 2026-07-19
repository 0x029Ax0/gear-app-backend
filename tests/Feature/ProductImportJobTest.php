<?php

use App\Jobs\ImportProductFromUrl;
use App\Models\Category;
use App\Models\GearItem;
use App\Models\ProductImport;
use App\Models\User;
use App\Services\ProductImports\BoundedProductFetcher;
use App\Services\ProductImports\ProductParser;
use App\Services\ProductImports\SafeImageDownloader;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('completes a product import job and creates an owned gear item', function (): void {
    Http::fake([
        'https://1.1.1.1/product' => Http::response(json_encode([
            '@type' => 'Product', 'name' => 'Imported tent',
            'weight' => '2 kg', 'offers' => ['price' => '99.99', 'priceCurrency' => 'eur'],
        ]), 200, ['Content-Type' => 'application/ld+json']),
    ]);
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $import = ProductImport::factory()->for($user)->create([
        'category_id' => $category->id,
        'source_url' => 'https://1.1.1.1/product',
        'quantity' => 2,
        'in_possession' => true,
        'ordered' => false,
    ]);

    (new ImportProductFromUrl($import->id))->handle(
        app(BoundedProductFetcher::class),
        app(ProductParser::class),
        app(SafeImageDownloader::class),
    );

    $import->refresh();
    expect($import->status)->toBe(ProductImport::COMPLETED)
        ->and($import->gear_item_id)->not->toBeNull();
    $item = GearItem::findOrFail($import->gear_item_id);
    expect($item->name)->toBe('Imported tent')
        ->and($item->quantity)->toBe(2)
        ->and($item->weight_grams)->toBe(2000)
        ->and($item->price_minor)->toBe(9999)
        ->and($item->currency_code)->toBe('EUR')
        ->and($item->user_id)->toBe($user->id);
});

it('records a stable failure when product fetching fails', function (): void {
    Http::fake(['https://1.1.1.1/fail' => Http::response('', 500)]);
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $import = ProductImport::factory()->for($user)->create(['category_id' => $category->id, 'source_url' => 'https://1.1.1.1/fail']);

    (new ImportProductFromUrl($import->id))->handle(app(BoundedProductFetcher::class), app(ProductParser::class), app(SafeImageDownloader::class));

    expect($import->refresh()->status)->toBe(ProductImport::FAILED)
        ->and($import->failure_code)->toBe('FETCH_FAILED')
        ->and($import->failure_message)->toContain('could not be fetched');
});

it('does nothing for missing, expired, or already processed imports', function (): void {
    $user = User::factory()->create();
    $expired = ProductImport::factory()->for($user)->create(['expires_at' => now()->subMinute()]);
    $completed = ProductImport::factory()->for($user)->create(['status' => ProductImport::COMPLETED]);

    $job = fn (int $id) => (new ImportProductFromUrl($id))->handle(app(BoundedProductFetcher::class), app(ProductParser::class), app(SafeImageDownloader::class));
    $job(999999);
    $job($expired->id);
    $job($completed->id);

    expect($expired->refresh()->status)->toBe(ProductImport::PENDING)
        ->and($completed->refresh()->status)->toBe(ProductImport::COMPLETED);
});

it('fails an import when no category is available', function (): void {
    Http::fake(['https://1.1.1.1/product' => Http::response('<title>Tent</title>', 200, ['Content-Type' => 'text/html'])]);
    $user = User::factory()->create();
    $import = ProductImport::factory()->for($user)->create(['source_url' => 'https://1.1.1.1/product', 'category_id' => null]);

    (new ImportProductFromUrl($import->id))->handle(app(BoundedProductFetcher::class), app(ProductParser::class), app(SafeImageDownloader::class));

    expect($import->refresh()->status)->toBe(ProductImport::FAILED)
        ->and($import->failure_code)->toBe('MISSING_CATEGORY');
});

it('cleans up expired product imports with the artisan command', function (): void {
    $user = User::factory()->create();
    $expired = ProductImport::factory()->for($user)->create(['expires_at' => now()->subMinute()]);
    $active = ProductImport::factory()->for($user)->create(['expires_at' => now()->addHour()]);

    Artisan::call('product-imports:cleanup');

    $this->assertDatabaseMissing('product_imports', ['id' => $expired->id]);
    $this->assertDatabaseHas('product_imports', ['id' => $active->id]);
    expect(Artisan::output())->toContain('Deleted 1 expired product imports.');
});
