<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\GearItem;
use App\Models\ProductImport;
use App\Services\ProductImports\BoundedProductFetcher;
use App\Services\ProductImports\ProductImportException;
use App\Services\ProductImports\ProductParser;
use App\Services\ProductImports\SafeImageDownloader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportProductFromUrl implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $importId) {}

    public function handle(BoundedProductFetcher $fetcher, ProductParser $parser, SafeImageDownloader $images): void
    {
        $import = ProductImport::find($this->importId);
        if ($import === null || $import->status !== ProductImport::PENDING || $import->expires_at->isPast()) {
            return;
        }
        $import->update(['status' => ProductImport::PROCESSING, 'started_at' => now()]);
        try {
            $page = $fetcher->fetch($import->source_url);
            $parsed = $parser->parse($page['body'], $page['url']);
            $category = $import->category ?: Category::query()->where('is_system', true)->first();
            if ($category === null) {
                throw new ProductImportException('MISSING_CATEGORY', 'A category is required before importing a product.');
            }
            $imagePath = null;
            if ($parsed['image_url'] !== null) {
                try {
                    $imagePath = $images->download($parsed['image_url'], $import->user_id, $import->id);
                } catch (ProductImportException) {
                    $imagePath = null;
                }
            }
            $item = GearItem::create([
                'user_id' => $import->user_id,
                'category_id' => $category->id,
                'name' => $parsed['name'],
                'quantity' => $import->quantity,
                'weight_grams' => $parsed['weight_grams'] ?? 0,
                'price_minor' => $parsed['price_minor'],
                'currency_code' => $parsed['price_minor'] === null ? null : ($parsed['currency_code'] ?? $import->user->preferred_currency ?? 'EUR'),
                'product_url' => $parsed['product_url'],
                'image_path' => $imagePath,
                'image_source_url' => $parsed['image_url'],
                'in_possession' => $import->in_possession,
                'ordered' => $import->ordered,
                'imported_at' => now(),
            ]);
            $import->update(['status' => ProductImport::COMPLETED, 'result' => $parsed, 'gear_item_id' => $item->id, 'completed_at' => now()]);
        } catch (ProductImportException $exception) {
            $this->failImport($import, $exception->failureCode, $exception->getMessage());
        } catch (\Throwable) {
            $this->failImport($import, 'IMPORT_FAILED', 'The product could not be imported.');
        }
    }

    private function failImport(ProductImport $import, string $code, string $message): void
    {
        $import->update(['status' => ProductImport::FAILED, 'failure_code' => $code, 'failure_message' => $message, 'completed_at' => now()]);
    }
}
