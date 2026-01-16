<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifyService;
use App\Services\ShopifyProductService;
use App\Services\ShopifyLoggerService;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;


class FetchShopifyProducts extends Command
{
    protected $signature = 'shopify:fetch-products';
    protected $description = 'Fetch all products from Shopify and save to database';
    protected $shopifyProductService;

    public function __construct(ShopifyService $shopifyService, ShopifyProductService $shopifyProductService)
    {
        parent::__construct();
        $this->shopifyService = $shopifyService;
        $this->shopifyProductService = $shopifyProductService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $products = $this->shopifyProductService->getAllProducts();

        try {
            Log::info('Create Product in DB');

            foreach ($products as $productData) {
                $shopifyProductId = $productData['id'];
                $createdAt = isset($productData['created_at']) ? $productData['created_at'] : Carbon::now()->format('Y-m-d H:i:s');
                $createdAt = Carbon::parse($createdAt)->format('Y-m-d H:i:s');
                $updateddAt = isset($productData['updated_at']) ? $productData['updated_at'] : Carbon::now()->format('Y-m-d H:i:s');
                $updateddAt = Carbon::parse($createdAt)->format('Y-m-d H:i:s');
                $publishedAt = isset($productData['published_at']) ? $productData['published_at'] : Carbon::now()->format('Y-m-d H:i:s');
                $publishedAt = Carbon::parse($publishedAt)->format('Y-m-d H:i:s');
                $product = Product::updateOrCreate(
                    ['shopify_product_id' => $shopifyProductId],
                    [
                        'title' => $productData['title'],
                        'description' => $productData['body_html'] ?? '',
                        'vendor' => $productData['vendor'],
                        'product_type' => $productData['product_type'],
                        'tags' => $productData['tags'],
                        'shopify_created_at' => $createdAt,
                        'shopify_updated_at' => $updateddAt,
                        'shopify_published_at' => $publishedAt,
                        'status' => $productData['status'] ?? 'active',
                        'variants' => json_encode($productData['variants']),
                        'images' => json_encode($productData['images']),
                        'options' => json_encode($productData['options']),
                    ]
                );

                // Save Variants
                foreach ($productData['variants'] as $variantData) {
                    ProductVariant::updateOrCreate(
                        ['shopify_variant_id' => $variantData['id']],
                        [
                            'product_id' => $product->id,
                            'shopify_product_id' => $shopifyProductId,
                            'sku' => $variantData['sku'],
                            'barcode' => $variantData['barcode'],
                            'price' => $variantData['price'],
                            'compare_at_price' => $variantData['compare_at_price'],
                            'title' => $variantData['title'],
                            'weight' => $variantData['weight'],
                            'weight_unit' => $variantData['weight_unit'],
                            'inventory_quantity' => $variantData['inventory_quantity'] ?? 0,
                            'old_inventory_quantity' => $variantData['old_inventory_quantity'] ?? 0,
                        ]
                    );
                }

                // Save Images
                foreach ($productData['images'] as $imageData) {
                    ProductImage::updateOrCreate(
                        ['shopify_image_id' => $imageData['id']],
                        [
                            'product_id' => $product->id,
                            'shopify_product_id' => $shopifyProductId,
                            'position' => $imageData['position'],
                            'src' => $imageData['src'],
                        ]
                    );
                }

                // Save Options
                foreach ($productData['options'] as $optionData) {
                    ProductOption::updateOrCreate(
                        ['shopify_option_id' => $optionData['id']],
                        ['product_id' => $product->id, 'shopify_product_id' => $shopifyProductId, 'name' => $optionData['name']],
                        ['values' => json_encode($optionData['values']), 'position' => $optionData['position']]
                    );
                }

                Log::info("Product {$shopifyProductId} Created in DB");
            }

            $this->info('Products fetched and saved successfully!');

        } catch (\Exception $e) {
             // Log the error
            Log::error('Create Product in DB Failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            echo $e->getMessage();exit;
            ShopifyLoggerService::log(
                'shopify_fetch_products',
                null,
                'Failed',
                'Product Failed to Create in DB: '. $e->getMessage(),
                null
            );
        }
    }
}
