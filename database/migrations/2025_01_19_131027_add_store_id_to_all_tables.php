<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $tables = [
            'orders',
            'order_line_items',
            'shopify_action_logs',
            'products',
            'product_variants',
            'product_images',
            'product_options',
            'product_collections',
            'product_discounts',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('store_id')->nullable()->after('id');
                $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        $tables = [
            'orders',
            'order_line_items',
            'shopify_action_logs',
            'products',
            'product_variants',
            'product_images',
            'product_options',
            'product_collections',
            'product_discounts',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign([$table->getTable() . '_store_id_foreign']);
                $table->dropColumn('store_id');
            });
        }
    }
};
