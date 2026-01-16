<?php

namespace App\Jobs;

use App\Models\ProductDiscount;
use App\Services\ShopifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\ShopifyLoggerService;

class RevertProductDiscountJob implements ShouldQueue
{
    use Queueable;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $productDiscount;

    public function __construct(ProductDiscount $productDiscount)
    {
        $this->productDiscount = $productDiscount;
    }

    public function handle(ShopifyService $shopifyService)
    {
        try {
            $client = $shopifyService->getClient();
            $discount = $this->productDiscount;

            $url = "variants/{$discount->shopify_variant_id}.json";
            $options = [
                'json' => [
                    'variant' => [
                        'id' => $discount->shopify_variant_id,
                        'price' => $discount->original_price,
                        'compare_at_price' => null,
                    ],
                ],
            ];

            $response = $shopifyService->makeRequestWithRetry($client, 'put', $url, $options);
            $body = json_decode($response->getBody(), true);

            if ($response->getStatusCode() === 200) {
                // Mark as reverted in the database
                $discount->update([
                    'status' => 'expired',
                    'shopify_synced' => true,
                    'is_reverted' => true,
                ]);

                Log::info("Reverted price for SKU {$discount->sku} successfully.");

                ShopifyLoggerService::log(
                    'revert_product_discounts',
                    null,
                    'success',
                    "Reverted price for SKU {$discount->sku} successfully.",
                    ['response' => $body]
                );
            } else {
                Log::error("Failed to revert price for SKU {$discount->sku}: Shopify API error.");
            }
        } catch (\Exception $e) {
            Log::error("Error reverting price for SKU {$discount->sku}: " . $e->getMessage());
        }
    }
}
