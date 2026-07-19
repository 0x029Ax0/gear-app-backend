<?php

use App\Services\FilesystemGearImageStorage;
use App\Services\ProductImports\BoundedProductFetcher;
use App\Services\ProductImports\ProductImportException;
use App\Services\ProductImports\ProductParser;
use App\Services\ProductImports\SafeImageDownloader;
use App\Services\ProductImports\SafeUrlValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('parses product JSON-LD and normalizes weight, money, currency, and images', function (): void {
    $parser = app(ProductParser::class);
    $body = '<script type="application/ld+json">'.json_encode([
        '@type' => 'Product',
        'name' => ' Alpine Tent ',
        'weight' => ['value' => '1.25 kg'],
        'offers' => ['price' => '€199,95', 'priceCurrency' => 'eur'],
        'image' => ['https://1.1.1.1/tent.jpg'],
    ]).'</script>';

    expect($parser->parse($body, 'https://1.1.1.1/tent'))->toMatchArray([
        'name' => 'Alpine Tent',
        'weight_grams' => 1250,
        'price_minor' => 19995,
        'currency_code' => 'EUR',
        'image_url' => 'https://1.1.1.1/tent.jpg',
        'product_url' => 'https://1.1.1.1/tent',
    ]);
});

it('parses Open Graph and title fallbacks and rejects pages without a name', function (): void {
    $parser = app(ProductParser::class);
    expect($parser->parse('<meta property="og:title" content="Mug"><meta property="og:image" content="https://1.1.1.1/mug.png">', 'https://1.1.1.1/mug')['name'])->toBe('Mug');
    expect($parser->parse('<title>Sleeping bag</title>', 'https://1.1.1.1/bag')['name'])->toBe('Sleeping bag');

    expect(fn () => $parser->parse('<html><body>Nothing</body></html>', 'https://1.1.1.1/empty'))
        ->toThrow(ProductImportException::class, 'product name');
});

it('normalizes decimal and unit formats in product metadata', function (): void {
    $parser = app(ProductParser::class);
    $body = '<script type="application/ld+json">'.json_encode([
        'name' => 'Compact stove', 'weight' => '350 g', 'price' => '1,234.50', 'priceCurrency' => 'usd',
    ]).'</script>';

    expect($parser->parse($body, 'https://1.1.1.1/stove'))->toMatchArray([
        'weight_grams' => 350,
        'price_minor' => 123450,
        'currency_code' => 'USD',
    ]);
});

it('blocks unsafe URL schemes, credentials, private IPs, and unresolved hosts', function (): void {
    $validator = app(SafeUrlValidator::class);

    foreach (['file:///etc/passwd', 'https://user:pass@1.1.1.1/private', 'http://127.0.0.1/admin', 'https://nonexistent.invalid/item'] as $url) {
        expect(fn () => $validator->validate($url))->toThrow(ProductImportException::class);
    }
    expect($validator->validate('https://1.1.1.1/public'))->toBe('https://1.1.1.1/public');
    expect($validator->validateRedirect('https://1.1.1.2/redirect'))->toBe('https://1.1.1.2/redirect');
});

it('fetches bounded HTML and follows only validated redirects', function (): void {
    Http::fake([
        'https://1.1.1.1/start' => Http::response('', 302, ['Location' => '/product']),
        'https://1.1.1.1/product' => Http::response('<title>Tent</title>', 200, ['Content-Type' => 'text/html']),
    ]);

    $result = app(BoundedProductFetcher::class)->fetch('https://1.1.1.1/start');
    expect($result['url'])->toBe('https://1.1.1.1/product')
        ->and($result['body'])->toContain('Tent');
    Http::assertSentCount(2);
});

it('rejects fetch failures, excessive redirects, oversized responses, and unsupported content', function (): void {
    Http::fake([
        'https://1.1.1.1/fail' => Http::response('', 500),
        'https://1.1.1.1/large' => Http::response(str_repeat('x', 20), 200, ['Content-Type' => 'text/html']),
        'https://1.1.1.1/image' => Http::response('bytes', 200, ['Content-Type' => 'image/png']),
    ]);

    expect(fn () => app(BoundedProductFetcher::class)->fetch('https://1.1.1.1/fail'))
        ->toThrow(ProductImportException::class, 'could not be fetched');
    config(['product_imports.max_bytes' => 10]);
    expect(fn () => app(BoundedProductFetcher::class)->fetch('https://1.1.1.1/large'))
        ->toThrow(ProductImportException::class, 'too large');
    config(['product_imports.max_bytes' => 2_000_000]);
    expect(fn () => app(BoundedProductFetcher::class)->fetch('https://1.1.1.1/image'))
        ->toThrow(ProductImportException::class, 'not HTML or JSON');
});

it('rejects too many redirects and redirects without a location', function (): void {
    config(['product_imports.max_redirects' => 1]);
    Http::fake([
        'https://1.1.1.1/one' => Http::response('', 302, ['Location' => '/two']),
        'https://1.1.1.1/two' => Http::response('', 302, ['Location' => '/three']),
        'https://1.1.1.1/empty' => Http::response('', 302),
    ]);

    expect(fn () => app(BoundedProductFetcher::class)->fetch('https://1.1.1.1/one'))
        ->toThrow(ProductImportException::class, 'redirected too many times');
    expect(fn () => app(BoundedProductFetcher::class)->fetch('https://1.1.1.1/empty'))
        ->toThrow(ProductImportException::class, 'invalid redirect');
});

it('downloads only supported image content within the configured limit', function (): void {
    Storage::fake('public');
    Http::fake(['https://1.1.1.1/tent.png' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true), 200, ['Content-Type' => 'image/png'])]);

    $path = app(SafeImageDownloader::class)->download('https://1.1.1.1/tent.png', 7, 9);
    expect($path)->toMatch('/^gear-images\/7\/imports\/9\/.+\.png$/');
    Storage::disk('public')->assertExists($path);
});

it('ignores failed, oversized, and unsupported image downloads', function (): void {
    Storage::fake('public');
    Http::fake([
        'https://1.1.1.1/fail' => Http::response('', 404),
        'https://1.1.1.1/large' => Http::response(str_repeat('x', 20), 200),
        'https://1.1.1.1/text' => Http::response('not an image', 200),
    ]);

    expect(app(SafeImageDownloader::class)->download('https://1.1.1.1/fail', 1, 1))->toBeNull();
    config(['product_imports.max_image_bytes' => 10]);
    expect(app(SafeImageDownloader::class)->download('https://1.1.1.1/large', 1, 2))->toBeNull();
    config(['product_imports.max_image_bytes' => 5_000_000]);
    expect(app(SafeImageDownloader::class)->download('https://1.1.1.1/text', 1, 3))->toBeNull();
});

it('stores and deletes gear images through the filesystem abstraction', function (): void {
    Storage::fake('public');
    $storage = app(FilesystemGearImageStorage::class);
    $image = UploadedFile::fake()->image('gear.png');

    expect($storage->store($image, 'gear-images/1/gear.png'))->toBe('gear-images/1/gear.png');
    Storage::disk('public')->assertExists('gear-images/1/gear.png');
    $storage->delete('gear-images/1/gear.png');
    Storage::disk('public')->assertMissing('gear-images/1/gear.png');
    $storage->delete(null);
});
