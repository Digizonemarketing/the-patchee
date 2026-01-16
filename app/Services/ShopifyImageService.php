<?php

namespace App\Services;

use App\Services\ShopifyService;
use Illuminate\Support\Facades\Log;

class ShopifyImageService
{

    protected $shopifyService;

    public function __construct(ShopifyService $shopifyService)
    {
        $this->shopifyService = $shopifyService;
    }

    public function replaceShopifyProductImage($productId, $oldImageId, $newImageUrl, $shopifyDomain, $accessToken)
    {
       /* $client = new Client([
            'base_uri' => "https://{$shopifyDomain}/admin/api/2023-10/",
            'headers' => [
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ],
        ]);*/

        $client = $this->shopifyService->getClient();

        try {
            // Step 1: Delete the old image
            $client->delete("products/{$productId}/images/{$oldImageId}.json");

            // Step 2: Add the new image
            $response = $client->post("products/{$productId}/images.json", [
                'json' => [
                    'image' => [
                        'src' => $newImageUrl,
                    ],
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            // Handle errors
            return ['error' => $e->getMessage()];
        }
    }

    function replaceImagesBySku($sku, $base64Images, $shopifyDomain, $accessToken)
    {
        $client = new Client([
            'base_uri' => "https://soorajtest.myshopify.com/admin/api/2023-10/",
            'headers' => [
                'X-Shopify-Access-Token' => '',
                'Content-Type' => 'application/json',
            ],
        ]);

        try {
            // Step 1: Get Product ID by SKU
            $response = $client->get("products.json", [
                'query' => [
                    'fields' => 'id,variants',
                    'sku' => $sku,
                ],
            ]);

            $products = json_decode($response->getBody()->getContents(), true)['products'];
            if (empty($products)) {
                return ['error' => "No product found for SKU: $sku"];
            }

            $productId = $products[0]['id'];

            // Step 2: Get Existing Images
            $imagesResponse = $client->get("products/{$productId}/images.json");
            $images = json_decode($imagesResponse->getBody()->getContents(), true)['images'];

            // Step 3: Delete All Images
            foreach ($images as $image) {
                $client->delete("products/{$productId}/images/{$image['id']}.json");
            }

            // Step 4: Add New Images
            foreach ($base64Images as $base64Image) {
                $client->post("products/{$productId}/images.json", [
                    'json' => [
                        'image' => [
                            'attachment' => $base64Image,
                        ],
                    ],
                ]);
            }

            return ['success' => true];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

