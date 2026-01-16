<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Shop;

class ShopifySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shopifyDomain = env('SHOPIFY_STORE_URL');
        $accessToken = env('SHOPIFY_ACCESS_TOKEN');

        if (!$shopifyDomain || !$accessToken) {
            $this->command->error('Missing Shopify configuration in .env file.');
            return;
        }

        $shop = Shop::updateOrCreate(
            ['shopify_domain' => $shopifyDomain],
            ['access_token' => $accessToken]
        );

        if ($shop->wasRecentlyCreated) {
            $this->command->info("Shop {$shopifyDomain} created successfully.");
        } else {
            $this->command->info("Shop {$shopifyDomain} updated successfully.");
        }
    }
}
