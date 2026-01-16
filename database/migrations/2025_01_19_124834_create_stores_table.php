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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('store_name')->unique();
            $table->string('store_code')->unique(); // Unique code for each store
            $table->string('shop_domain')->unique(); // Shopify domain
            $table->string('store_url')->unique();
            $table->text('access_token'); // Shopify API token
            $table->string('erp_backend_url'); // ERP backend URL
            $table->string('erp_api_key')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
