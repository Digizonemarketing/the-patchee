<?php

namespace App\Jobs;

use App\Services\ShopifyLoggerService;
use Illuminate\Bus\Queueable;
use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReplaceProductImagesBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $products;
    protected $accessToken;
    protected $store;
    
    public function __construct($products, $accessToken, $store)
    {
        $this->products = $products;
        $this->accessToken = $accessToken;
        $this->store = $store;
    }

    public function handle()
    {
        // Log the response
        Log::info('Product Images Replace Queue Triggered!');

        $client = new Client([
            'base_uri' => "https://{env('SHOPIFY_STORE_URL')}/admin/api/2023-10/",
            'headers' => [
                'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN'),
                'Content-Type' => 'application/json',
            ],
        ]);

        // Log the response
        Log::info('product_images_replace_batch Queue', ['response' => $this->products]);
        $results = [];
       
        // Log the start of the batch processing
        ShopifyLoggerService::log(
            'product_images_replace_batch',
            null, // No specific resource_id since it's a batch
            'processing',
            'Started processing batch image aaaa replacement.',
            ['products' => $this->products]
        );

         // Log the response
        Log::info('product_images_replace_batch Queue', ['response' => $this->products]);

        foreach ($this->products as $product) {
            try {

                $productId = $product['shopify_product_id'];

                //  Get Existing Images
                $imagesResponse = $client->get("products/{$productId}/images.json");
                $existingImages = json_decode($imagesResponse->getBody()->getContents(), true)['images'];

                // Delete All Images
                foreach ($existingImages as $image) {
                    $deleteResponse = $client->delete("products/{$productId}/images/{$image['id']}.json");
                    Log::info("Deleted image ID {$image['id']}: " . $deleteResponse->getStatusCode());
                }

                // Add new images
                foreach ($product['image_urls'] as $imageUrl) {
                    $addResponse = $client->post("products/{$productId}/images.json", [
                        'json' => [
                            'image' => ['src' => $imageUrl]
                        ],
                    ]);
                    Log::info("Added image URL {$imageUrl}:  for ProductID: {$productId}" . $addResponse->getStatusCode());
                }

                // Log success for each product
                ShopifyLoggerService::log(
                    'product_image_replace',
                    $product['product_id'],
                    'success',
                    'Images replaced successfully.',
                    null
                );

                $results[] = [
                    'product_id' => $productId,
                    'status' => 'success',
                    'message' => 'Images replaced successfully.',
                ];

            } catch (\Exception $e) {
                // Log failure for each product
                ShopifyLoggerService::log(
                    'product_image_replace',
                    $productId,
                    'fail',
                    'Failed to replace images: ' . $e->getMessage(),
                    null
                );

                $results[] = [
                    'product_id' => $productId,
                    'status' => 'fail',
                    'message' => $e->getMessage(),
                ];
            }
        }

        // Log the end of batch processing
        ShopifyLoggerService::log(
            'product_images_replace_batch',
            null, // No specific resource_id
            'completed',
            'Batch processing completed.',
            ['results' => $results]
        );

        return $results;
    }
}
