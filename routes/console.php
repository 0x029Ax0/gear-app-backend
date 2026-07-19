<?php

use App\Models\ProductImport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('product-imports:cleanup', function (): void {
    $count = ProductImport::query()->where('expires_at', '<', now())->delete();
    $this->info("Deleted {$count} expired product imports.");
})->purpose('Delete expired product imports');
