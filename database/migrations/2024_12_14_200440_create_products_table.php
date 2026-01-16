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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('shopify_product_id')->unique(); 
            $table->string('erp_product_id')->nullable(); 
            $table->string('erp_spu')->nullable();
            $table->string('erp_category')->nullable();
            $table->string('erp_merchant_spu')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            $table->text('tags')->nullable();
            $table->string('status')->default('active');
            $table->json('variants')->nullable();
            $table->json('options')->nullable();
            $table->json('images')->nullable();
            $table->json('image')->nullable();
            $table->timestamp('shopify_created_at')->nullable();
            $table->timestamp('shopify_updated_at')->nullable();
            $table->timestamp('shopify_published_at')->nullable();
            $table->boolean('shopify_synced')->default(false);
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
