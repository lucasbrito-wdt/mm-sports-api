<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->foreignUlid('product_id')
                ->constrained('products')->cascadeOnDelete();
            $table->foreignUlid('attribute_value_id')
                ->constrained('attribute_values')->cascadeOnDelete();

            $table->primary(['product_id', 'attribute_value_id']);
            $table->index('attribute_value_id');
        });

        Schema::create('product_variant_axes', function (Blueprint $table) {
            $table->foreignUlid('product_id')
                ->constrained('products')->cascadeOnDelete();
            $table->foreignUlid('attribute_id')
                ->constrained('attributes')->cascadeOnDelete();
            $table->unsignedInteger('display_order')->default(0);

            $table->unique(['product_id', 'attribute_id']);
            $table->index('product_id');
        });

        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->foreignUlid('product_variant_id')
                ->constrained('product_variants')->cascadeOnDelete();
            $table->foreignUlid('attribute_id')
                ->constrained('attributes')->cascadeOnDelete();
            $table->foreignUlid('attribute_value_id')
                ->constrained('attribute_values')->cascadeOnDelete();

            $table->unique(['product_variant_id', 'attribute_id']);
            $table->index('attribute_value_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_attribute_values');
        Schema::dropIfExists('product_variant_axes');
        Schema::dropIfExists('product_attribute_values');
    }
};
