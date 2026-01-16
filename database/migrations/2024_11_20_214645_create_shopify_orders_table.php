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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable()->unique();
            $table->string('order_number')->nullable();
            $table->string('order_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->decimal('total_discounts', 10, 2)->nullable();
            $table->decimal('total_shipping_amount', 10, 2)->nullable();
            $table->string('total_shipping_currency')->nullable();
            $table->decimal('current_subtotal_price', 10, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->json('shipping_address')->nullable();
            $table->json('line_items')->nullable();
            $table->string('financial_status')->nullable();
            $table->string('fulfillment_status')->nullable();
            $table->timestamp('order_created_at')->nullable();
            $table->boolean('erp_synced')->default(false); // To track ERP sync

             // Individual shipping address columns
            $table->string('shipping_zip')->nullable();
            $table->string('shipping_country')->nullable();
            $table->string('shipping_country_code')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_address1')->nullable();
            $table->string('shipping_address2')->nullable();
            $table->decimal('shipping_latitude', 10, 7)->nullable();
            $table->decimal('shipping_longitude', 10, 7)->nullable();
            $table->string('shipping_name')->nullable();
            $table->string('shipping_first_name')->nullable();
            $table->string('shipping_last_name')->nullable();
            $table->string('shipping_phone')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
