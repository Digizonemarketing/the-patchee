<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Services\ShopifyService;
use App\Models\Setting;
use App\Jobs\ReplaceProductImagesBatch;
use App\Services\ShopifyLoggerService;
use Illuminate\Support\Facades\Log;

class ProductImageController extends Controller
{
    protected $shopifyService;

    public function __construct(ShopifyService $shopifyService)
    {
        $this->shopifyService = $shopifyService;
    }

    public function pauseForRateLimit($response)
    {
        if ($response->hasHeader('X-Shopify-Shop-Api-Call-Limit')) {
            $rateLimit = explode('/', $response->getHeader('X-Shopify-Shop-Api-Call-Limit')[0]);
            $currentCalls = (int)$rateLimit[0];
            $maxCalls = (int)$rateLimit[1];

            // Pause if near the limit
            if ($currentCalls >= $maxCalls - 1) {
                sleep(1); // Shopify API allows 2 requests per second;
            }
        }
    }

    public function replaceProductImagesBatch(Request $request)
    {
        try {

            $client = $this->shopifyService->getClient();

            $validatedData = $request->validate([
                'products' => 'required|array',
                'products.*.shopify_product_id' => 'required|integer',
                'products.*.image_urls' => 'required|array',
                'products.*.image_urls.*' => 'required|url',
            ]);

            $products = $request->input('products');
            $accessToken = env('SHOPIFY_ACCESS_TOKEN');
            $store = env('SHOPIFY_STORE_URL');

            // Dispatch a batch update job
            ReplaceProductImagesBatch::dispatch($products, $accessToken, $store);

            // Log the batch request
            ShopifyLoggerService::log(
                'product_images_replace_batch',
                null, // No specific resource_id
                'queued',
                'Batch Product Images replacement job queued.',
                ['products' => $products]
            );

            return response()->json([
                'message' => 'Batch Product Images replacement job queued.',
                'products' => $products
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to Batch image replacement job queued: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to replace product images.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function replaceProductImages(Request $request)
    {
        $storeId = $request->input('store_id');
        try {
            $client = $this->shopifyService->getClient($storeId);

            if ($client == null) {
                return response(['message' => 'Invalid API key or access token', 'code' => 401]);
            }

            // Validate incoming request
            $request->validate([
                'products' => 'required|array',
                'products.*.shopify_product_id' => 'required|integer',
                'products.*.images' => 'required|array',
                'products.*.images.*.url' => 'required|url',
                'products.*.images.*.variant_id' => 'nullable|integer',
            ]);

            $data = json_decode($request->getContent(), true);

            // Log the batch request start
            ShopifyLoggerService::log(
                $storeId,
                'product_images_replace_manual',
                null,
                'start',
                'Product Images replacement started.',
                ['products' => $data]
            );

            $updatedProducts = [];
            $failedProducts = [];
            $logs = [];

            foreach ($request->input('products') as $product) {
                $productId = $product['shopify_product_id'];
                $images = $product['images'];
                $productLogs = [
                    'product_id' => $productId,
                    'success_images' => [],
                    'failed_images' => [],
                    'deleted_images' => [],
                    'delete_failures' => [],
                    'status' => 'pending',
                    'message' => '',
                ];

                try {
                    // Fetch existing images and delete them
                    $this->fetchAndDeleteExistingImages($client, $productId, $productLogs);

                    // Add new images
                    $this->addNewImages($client, $productId, $images, $productLogs);

                    // Update logs based on outcomes
                    $this->updateProductLogs($productLogs, $updatedProducts, $failedProducts);
                } catch (\Exception $e) {
                    $productLogs['status'] = 'fail';
                    $productLogs['message'] = "Error processing product: {$e->getMessage()}";
                    $failedProducts[] = $productId;
                }

                $logs[$productId] = $productLogs;
            }

            // Construct response
            $response = [
                'status' => empty($failedProducts) ? 'success' : (empty($updatedProducts) ? 'fail' : 'partial'),
                'message' => empty($failedProducts)
                    ? 'All products processed successfully.'
                    : (empty($updatedProducts) ? 'All products failed to process.' : 'Some products processed successfully, some failed.'),
                'data' => $logs,
                'errors' => $failedProducts,
            ];

            Log::info('Images Replace Manual Detailed Logs:', $response);

            ShopifyLoggerService::log(
                $storeId,
                'product_images_replace_manual',
                null,
                'completed',
                'Product Images replacement completed.',
                ['replace_images_response' => $response]
            );

            return response()->json($response, 200);

        } catch (\Exception $e) {
            Log::error('Failed to replace product images: ' . $e->getMessage());
            ShopifyLoggerService::log(
                $storeId,
                'product_images_replace_manual',
                null,
                'failed',
                "Error: {$e->getMessage()}",
                []
            );
        }
    }

    /**
     * Fetch and delete existing images for a product.
     */
    private function fetchAndDeleteExistingImages(Client $client, $productId, &$productLogs)
    {
        $imagesResponse = $client->get("products/{$productId}/images.json");
        $existingImages = json_decode($imagesResponse->getBody()->getContents(), true)['images'] ?? [];

        foreach ($existingImages as $image) {
            try {
                $deleteResponse = $client->delete("products/{$productId}/images/{$image['id']}.json");
                $this->pauseForRateLimit($deleteResponse);

                if (in_array($deleteResponse->getStatusCode(), [200, 204])) {
                    $productLogs['deleted_images'][] = [
                        'image_id' => $image['id'],
                        'message' => "Successfully deleted image ID {$image['id']}",
                    ];
                } else {
                    $productLogs['delete_failures'][] = [
                        'image_id' => $image['id'],
                        'message' => "Failed to delete image ID {$image['id']}. Status code: {$deleteResponse->getStatusCode()}",
                    ];
                }
            } catch (\Exception $e) {
                $productLogs['delete_failures'][] = [
                    'image_id' => $image['id'],
                    'message' => "Error deleting image ID {$image['id']}: {$e->getMessage()}",
                ];
            }
        }
    }

    /**
     * Add new images to the product.
     */
    private function addNewImages(Client $client, $productId, $images, &$productLogs)
    {
        foreach ($images as $image) {
            try {
                $imagePayload = ['image' => ['src' => $image['url']]];
                if (!empty($image['variant_id'])) {
                    $imagePayload['image']['variant_ids'] = [$image['variant_id']];
                }

                $addResponse = $client->post("products/{$productId}/images.json", [
                    'json' => $imagePayload,
                ]);
                $this->pauseForRateLimit($addResponse);

                $responseBody = json_decode($addResponse->getBody()->getContents(), true);
                if ($addResponse->getStatusCode() === 200 && isset($responseBody['image'])) {

                    $productLogs['success_images'][] = [
                        'image_url' => $image['url'],
                        'variant_id' => $image['variant_id'] ?? null,
                        'updated_image_url' => $responseBody['image']['src'],
                        'image_id' => $responseBody['image']['id'],
                        'message' => "Successfully added image URL {$image['url']}",
                    ];
                } else {
                    $productLogs['failed_images'][] = [
                        'image_url' => $image['url'],
                        'variant_id' => $image['variant_id'] ?? null,
                        'message' => "Failed to add image URL {$image['url']}. Status code: {$addResponse->getStatusCode()}",
                    ];
                }
            } catch (\Exception $e) {
                $productLogs['failed_images'][] = [
                    'image_url' => $image['url'],
                    'variant_id' => $image['variant_id'] ?? null,
                    'message' => "Error adding image URL {$image['url']}: {$e->getMessage()}",
                ];
            }
        }
    }

    /**
     * Update product log status based on image processing outcomes.
     */
    private function updateProductLogs(&$productLogs, &$updatedProducts, &$failedProducts)
    {
        if (empty($productLogs['failed_images']) && empty($productLogs['delete_failures'])) {
            $productLogs['status'] = 'success';
            $productLogs['message'] = 'All images processed successfully';
            $updatedProducts[] = $productLogs['product_id'];
        } else {
            $productLogs['status'] = 'partial';
            $productLogs['message'] = 'Some images failed to process';
            $failedProducts[] = $productLogs['product_id'];
        }
    }
}

