<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class PopulateStoreIdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
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
            DB::table($table)->whereNull('store_id')->update(['store_id' => 1]);
        }
    }
}
