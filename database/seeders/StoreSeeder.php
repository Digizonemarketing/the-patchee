<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;
use Illuminate\Support\Facades\Crypt;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {   
        $slToken = env('SL_SHOPIFY_ACCESS_TOKEN');
        $bdToken = env('BD_SHOPIFY_ACCESS_TOKEN');
        $pkToken = env('PK_SHOPIFY_ACCESS_TOKEN');
        $kzToken = env('KZ_SHOPIFY_ACCESS_TOKEN');
        $testToken = env('TEST_SHOPIFY_ACCESS_TOKEN');

        $stores = [
            [
                'store_name' => 'The Patchee Sri Lanka',
                'store_code' => 'lk',
                'shop_domain' => 'f10c92-37.myshopify.com',
                'store_url' => 'https://www.thepatchee.lk',
                'access_token' => Crypt::encrypt($slToken),
                'erp_backend_url' => 'https://admin.thepatchee.lk',
                'erp_api_key' => '',
            ],
            [
                'store_name' => 'The Patchee Bangladesh',
                'store_code' => 'bd',
                'shop_domain' => 'kr16jt-dc.myshopify.com',
                'store_url' => 'https://www.thepatchee.com/',
                'access_token' => Crypt::encrypt($bdToken),
                'erp_backend_url' => 'https://www.admin.thepatchee.com',
                'erp_api_key' => '',
            ],
            [
                'store_name' => 'The Patchee Pakistan',
                'store_code' => 'pk',
                'shop_domain' => 'aodour-pk.myshopify.com',
                'store_url' => 'https://aodour-pk.myshopify.com',
                'access_token' => Crypt::encrypt($pkToken),
                'erp_backend_url' => 'https://admin.aodour.pk',
                'erp_api_key' => '',
            ],
            [
                'store_name' => 'The Patchee Kazakhstan',
                'store_code' => 'kz',
                'shop_domain' => 'kz.myshopify.com',
                'store_url' => 'https://kz.myshopify.com',
                'access_token' => Crypt::encrypt($kzToken),
                'erp_backend_url' => 'https://kz.thepatchee.test',
                'erp_api_key' => '',
            ],
            [
                'store_name' => 'The Test Store',
                'store_code' => 'test',
                'shop_domain' => 'soorajtesting.myshopify.com',
                'store_url' => 'https://soorajtesting.myshopify.com',
                'access_token' => Crypt::encrypt($testToken),
                'erp_backend_url' => 'https://admin.thepatchee.test',
                'erp_api_key' => '',
            ],
        ];

        foreach ($stores as $store) {
            Store::updateOrCreate(
                ['store_code' => $store['store_code']],
                $store
            );
        }
    }
}

