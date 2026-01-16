<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Models\ProductCollection;
use App\Services\ShopifyLoggerService;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use App\Models\ProductDiscount;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use App\Services\ShopifyService;

class ShopifyProductDiscountController extends Controller
{
    protected $client;

    protected $request;

    protected $shopifyService;

    public function __construct(ShopifyService $shopifyService)
    {
        $this->shopifyService = $shopifyService;
    }

    public function applyDiscounts(Request $request)
    {
        $results = [];
        $failed = [];
        $success = [];
        $storeId = $request->input('store_id');
        try {
            // Initialize Shopify client
            $client = $this->shopifyService->getClient($storeId);
            if ($client == null) {
                return response(['message' => 'Invalid API key or access token', 'code' => 401]);
            }

            // Validate request data
            $validated = $request->validate([
                'discounts' => 'required|array',
                'discounts.*.sku' => 'required|string',
                'discounts.*.shopify_product_id' => 'required|string',
                'discounts.*.shopify_variant_id' => 'required|string',
                'discounts.*.original_price' => 'required|numeric',
                'discounts.*.discounted_price' => 'nullable|numeric',
                'discounts.*.start_date' => 'nullable|date',
                'discounts.*.end_date' => 'nullable|date',
            ]);

            $discounts = $validated['discounts'];

            ShopifyLoggerService::log(
                $storeId,
                'product_discounts',
                null,
                'start',
                'Create Product Discounts',
                ['discounts_payload' => $validated]
            );

            // Process each discount
            foreach ($discounts as $data) {
                $sku = $data['sku'];
                $originalPrice = $data['original_price'];
                $variantId = $data['shopify_variant_id'];
                $productId = $data['shopify_product_id'];
                $discountedPrice = $data['discounted_price'];

                // Safely retrieve optional dates or set to null if not provided
                $startDate = isset($data['start_date']) ? Carbon::parse($data['start_date']) : null;
                $endDate = isset($data['end_date']) ? Carbon::parse($data['end_date']) : null;
                $currentDate = now();

                // Validate date range
                if ($startDate && $currentDate->lt($startDate)) {
                    $failed[] = [
                        'sku' => $sku,
                        'shopify_variant_id' => $variantId,
                        'shopify_product_id' => $productId,
                        'message' => "Discount start date {$startDate->toDateString()} has not arrived."
                    ];
                    continue;
                }

                if ($endDate && $currentDate->gt($endDate)) {
                    $failed[] = [
                        'sku' => $sku,
                        'shopify_variant_id' => $variantId,
                        'shopify_product_id' => $productId,
                        'message' => "Discount end date {$endDate->toDateString()} has passed."
                    ];
                    continue;
                }

                // Update variant pricing logic
                $response = $this->updateVariantPricing($storeId, $client, $variantId, $discountedPrice, $originalPrice, $sku);
                $results[$sku] = $response;

                if ($response['status'] === 'active') {
                    $success[] = ['sku' => $sku, 'shopify_variant_id' => $variantId, 'shopify_product_id' => $productId];
                } else {
                    $failed[] = [
                        'sku' => $sku,
                        'shopify_variant_id' => $variantId,
                        'shopify_product_id' => $productId,
                        'message' => $response['message']
                    ];
                }

                // Save or update the discount in the database
                ProductDiscount::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'store_id' => $storeId,
                        'shopify_product_id' => $productId,
                        'shopify_variant_id' => $variantId,
                        'original_price' => $originalPrice,
                        'discounted_price' => $discountedPrice,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => $response['status'],
                        'is_reverted' => false,
                        'shopify_synced' => $response['shopify_synced']
                    ]
                );
            }

            // Log the final response
            ShopifyLoggerService::log(
                $storeId,
                'product_discounts',
                null,
                'Completed',
                'Successfully updated discounted prices',
                [
                    'results' => $results,
                    'success' => $success,
                    'failed' => $failed,
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => empty($failed) ? 'All discounts applied successfully.' : 'Some discounts failed to apply.',
                'success' => $success,
                'failed' => $failed,
            ]);

        } catch (\Exception $e) {
            Log::error('Discount Error: ' . $e->getMessage());
            ShopifyLoggerService::log(
                $storeId,
                'product_discounts',
                null,
                'error',
                'An error occurred during discount application',
                ['exception' => $e->getMessage()]
            );

            return response()->json([
                'status' => 'failed',
                'message' => 'Failed to create discounted prices: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateVariantPricing($storeId, $client, $variantId, $discountedPrice, $originalPrice, $sku): array
    {
        try {
            $url = "variants/{$variantId}.json";

            if ($discountedPrice) {
                $options = [
                    'json' => [
                        'variant' => [
                            'id' => $variantId,
                            'price' => $discountedPrice,
                            'compare_at_price' => $originalPrice,
                        ],
                    ],
                ];
            } else {
                $options = [
                    'json' => [
                        'variant' => [
                            'id' => $variantId,
                            'price' => $originalPrice,
                            'compare_at_price' => null,
                        ],
                    ],
                ];
            }

            $response = $this->shopifyService->makeRequestWithRetry($storeId, $client, 'put', $url, $options);
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);

            if ($statusCode === 200 && isset($body['variant'])) {
                Log::info("Discount updated successfully for Variant ID: {$variantId}, SKU: {$sku}");
                return [
                    'status' => 'active',
                    'shopify_synced' => true,
                    'status_code' => $statusCode,
                    'message' => "Successfully updated discount for Variant ID: {$variantId} and SKU: {$sku}.",
                    'data' => $body['variant'],
                ];
            }
        } catch (\Exception $e) {
            Log::error("Error updating variant ID: {$variantId}, SKU: {$sku}. Error: {$e->getMessage()}");
             return [
                'status' => 'failed',
                'shopify_synced' => false,
                'message' => "Error updating discounted prices for Variant ID: {$variantId}, SKU: {$sku}. Error: {$e->getMessage()}",
            ];
        }

        return [
            'status' => 'failed',
            'shopify_synced' => false,
            'message' => "Error updating discounted prices",
        ];
    }
}
