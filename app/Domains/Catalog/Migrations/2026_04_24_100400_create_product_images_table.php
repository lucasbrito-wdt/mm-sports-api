<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')
                ->constrained('products')->cascadeOnDelete();
            $table->foreignUlid('attribute_value_id')
                ->nullable()
                ->constrained('attribute_values')
                ->nullOnDelete();
            $table->string('url');
            $table->string('alt')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'attribute_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
