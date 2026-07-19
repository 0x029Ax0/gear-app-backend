<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('normalized_name', 100);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'normalized_name']);
            $table->index(['is_system', 'normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
