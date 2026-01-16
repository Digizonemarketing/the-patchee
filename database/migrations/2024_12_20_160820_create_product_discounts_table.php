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
        Schema::create('product_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('shopify_product_id')->index(); // Shopify product ID
            $table->string('sku')->unique()->index(); // SKU of the variant
            $table->string('shopify_variant_id')->index(); // Shopify variant ID
            $table->decimal('discount_percentage', 10, 2)->nullable(); 
            $table->decimal('original_price', 10, 2)->nullable(); // Original price
            $table->decimal('discounted_price', 10, 2)->nullable(); // Discounted price
            $table->date('start_date')->nullable(); // Discount validity start date
            $table->date('end_date')->nullable(); // Discount validity end date
            $table->string('status')->default('pending');
            $table->boolean('shopify_synced')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_discounts');
    }
};
