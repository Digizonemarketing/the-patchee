<?php
namespace App\Services;

use GuzzleHttp\Client;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Log;
use App\Services\ShopifyService;

class ShopifyProductService
{
    protected $client;

    protected $shopifyService;

    public function __construct(ShopifyService $shopifyService)
    {   
        $this->shopifyService = $shopifyService;
    }

    public function getAllProducts()
    {
        $client = $this->shopifyService->getClient();

        if ($client == null) {
            return response(['message' => 'Invalid API key or access token', 'code' => 401]);
        }
        
        $products = [];
        $nextPageInfo = null;

        do {
            $response = $client->get('products.json', [
                'query' => $nextPageInfo ? ['page_info' => $nextPageInfo, 'limit' => 250] : ['limit' => 250],
            ]);

            $data = json_decode($response->getBody(), true);
            $products = array_merge($products, $data['products']);

            // Parse 'Link' header for pagination
            $linkHeader = $response->getHeader('Link');
            $nextPageInfo = $linkHeader ? $this->extractNextPageInfo($linkHeader[0]) : null;
        } while ($nextPageInfo);

        ShopifyLoggerService::log(
            'shopify_fetch__all_products',
            null,
            'Success',
            'Total number of products: '. count($products),
            null
        );

        return $products;
    }

    public function createShopifyProduct(Product $product)
    {   
        $result = ['status' => false, 'message' => 'Invalid API key or access token'];
        $client = $this->shopifyService->getClient();

        if ($client === null) {
            return $result;
        }

        try {
            $payload = $this->prepareProductPayload($product);
            Log::info('Shopify product sync payload:', $payload);

            $shopifyProductId = $product->shopify_product_id;

            if ($this->productExistsInShopify($client, $shopifyProductId)) {
                $result = $this->updateProductInShopify($client, $shopifyProductId, $payload);
            } else {
                $result = $this->createProductInShopify($client, $payload);
            }
        } catch (\Exception $e) {
            Log::error('Error syncing product with Shopify:', ['error' => $e->getMessage()]);
            $result = [
                'status' => false,
                'status_code' => 500,
                'message' => 'Error syncing product with Shopify: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }

        return $result;
    }

    private function prepareProductPayload($product)
    {
        $options = !empty($product->options) ? collect(json_decode($product->options))->map(function ($option) {
            return [
                'name' => $option->name,
                'values' => $option->values,
            ];
        })->toArray() : [];

        $variants = collect(json_decode($product->variants))->map(function ($variant) {
            return [
                'sku' => $variant->sku,
                'price' => $variant->price,
                'weight' => $variant->weight,
                'weight_unit' => $variant->weight_unit,
                'inventory_policy' => $variant->inventory_policy,
                'inventory_management' => $variant->inventory_management,
                'inventory_quantity' => $variant->inventory_quantity,
                'option1' => $variant->option1,
                'option2' => $variant->option2 ?? null,
                'option3' => $variant->option3 ?? null,
            ];
        })->toArray();

        return [
            'product' => [
                'title' => $product->title,
                'body_html' => $product->description,
                'vendor' => $product->vendor,
                'product_type' => $product->product_type,
                'tags' => $product->tags,
                'status' => $product->status,
                'options' => $options,
                'variants' => $variants,
            ],
        ];
    }

    private function productExistsInShopify($client, $productId)
    {
        try {
            $response = $client->get("products/{$productId}.json");
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            Log::warning('Shopify product existence check failed:', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function updateProductInShopify($client, $productId, $payload)
    {
        try {
            $response = $client->put("products/{$productId}.json", ['json' => $payload]);
            $statusCode = $response->getStatusCode();
            $updatedProduct = json_decode($response->getBody(), true);

            if ($statusCode === 200) {
                Log::info("Product updated successfully in Shopify. ID: {$productId}");
                return [
                    'status' => true,
                    'status_code' => $statusCode,
                    'message' => 'Product updated successfully in Shopify!',
                    'shopify_product_id' => $productId,
                    'data' => $updatedProduct['product'] ?? [],
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error updating product in Shopify:', ['error' => $e->getMessage()]);
        }

        return [
            'status' => false,
            'message' => 'Failed to update product in Shopify.',
        ];
    }

    private function createProductInShopify($client, $payload)
    {
        try {
            $response = $client->post('products.json', ['json' => $payload]);
            $statusCode = $response->getStatusCode();
            $newProduct = json_decode($response->getBody(), true);

            if ($statusCode === 201 && isset($newProduct['product']['id'])) {
                $productId = $newProduct['product']['id'];
                Log::info("Product created successfully in Shopify. ID: {$productId}");
                return [
                    'status' => true,
                    'status_code' => $statusCode,
                    'message' => 'Product created successfully in Shopify!',
                    'shopify_product_id' => $productId,
                    'data' => $newProduct['product'] ?? [],
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error creating product in Shopify:', ['error' => $e->getMessage()]);
        }

        return [
            'status' => false,
            'message' => 'Failed to create product in Shopify.',
        ];
    }

    /**
     * Extracts 'page_info' from the 'Link' header for pagination.
     */
    private function extractNextPageInfo(string $linkHeader): ?string
    {
        if (preg_match('/<[^>]+page_info=([^&>]+)>; rel="next"/', $linkHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }


    public function saveProductToDatabase(array $products)
    {
        foreach ($products as $productData) {
            $product = Product::updateOrCreate(
                ['shopify_product_id' => $productData['id']],
                [
                    'title' => $productData['title'],
                    'description' => $productData['body_html'] ?? '',
                    'vendor' => $productData['vendor'],
                    'product_type' => $productData['product_type'],
                    'tags' => $productData['tags'],
                    'created_at' => $productData['created_at'],
                    'updated_at' => $productData['updated_at'],
                    'tags' => $productData['tags'],
                    'status' => $productData['status'] ?? 'active',
                ]
            );

            // Save Variants
            foreach ($productData['variants'] as $variantData) {
                ProductVariant::updateOrCreate(
                    ['shopify_variant_id' => $variantData['id']],
                    [
                        'product_id' => $product->id,
                        'sku' => $variantData['sku'],
                        'price' => $variantData['price'],
                        'title' => $variantData['title'],
                        'inventory_quantity' => $variantData['inventory_quantity'] ?? 0,
                    ]
                );
            }

            // Save Images
            foreach ($productData['images'] as $imageData) {
                ProductImage::updateOrCreate(
                    ['shopify_image_id' => $imageData['id']],
                    [
                        'product_id' => $product->id,
                        'src' => $imageData['src'],
                    ]
                );
            }

            // Save Options
            foreach ($productData['options'] as $optionData) {
                ProductOption::updateOrCreate(
                    ['product_id' => $product->id, 'name' => $optionData['name']],
                    ['values' => json_encode($optionData['values'])]
                );
            }
        }

        return $data['products'] ?? [];
    }
}
