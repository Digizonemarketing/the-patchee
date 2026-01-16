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
        Schema::create('shopify_action_logs', function (Blueprint $table) {
        $table->id();
        $table->string('type'); // e.g., 'product_create', 'product_update', 'image_replace', 'order_create'
        $table->unsignedBigInteger('resource_id')->nullable(); // Product ID, Order ID, etc.
        $table->string('status'); // 'success', 'fail', or 'processing'
        $table->text('message');
        $table->json('payload')->nullable(); // request/response for debugging
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_action_logs');
    }
};
