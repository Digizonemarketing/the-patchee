<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('shopify_variant_id')->unique();
            $table->bigInteger('shopify_product_id');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('title')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->decimal('special_price', 10, 2)->nullable();
            $table->string('sku')->nullable();
            $table->integer('inventory_quantity')->nullable();
            $table->integer('old_inventory_quantity')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('weight_unit', 10)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
