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
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->boolean('is_owned')->default(false);
            $table->boolean('is_ordered')->default(false);
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'name']);
            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'is_owned', 'is_ordered']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gear_items');
    }
};
