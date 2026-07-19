<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gear_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 255);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('weight_grams')->default(0);
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->text('product_url')->nullable();
            $table->string('image_path')->nullable();
            $table->text('image_source_url')->nullable();
            $table->boolean('in_possession')->default(false);
            $table->boolean('ordered')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'name']);
            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'in_possession']);
            $table->index(['user_id', 'ordered']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gear_items');
    }
};
