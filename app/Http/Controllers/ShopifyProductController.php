<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Models\Setting;
use App\Services\ShopifyLoggerService;
use Illuminate\Support\Facades\Log;
use App\Services\ShopifyService;

class ShopifyProductController extends Controller
{
    protected $shopifyService;

    public function __construct(ShopifyService $shopifyService)
    {
        $this->shopifyService = $shopifyService;
    }

    public function createShopifyProduct(Request $request)
    {   
        $accessToken = (!Setting::where('key', 'testing_store')->value('value')) ? env('SHOPIFY_ACCESS_TOKEN') : env('SHOPIFY_ACCESS_TOKEN_TESTING');
        
        if ($request->header('X-Shopify-Access-Token') !== $accessToken) {
            return response(['message' => 'Invalid API key or access token', 'code' => 401]);
        }

        $client = $this->shopifyService->getClient();

        if ($client == null) {
            return response(['message' => 'Invalid API key or access token', 'code' => 401]);
        }
        
        // Validate incoming request
        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'vendor' => 'required|string',
            'type' => 'nullable|string',
            'status' => 'nullable|string|in:active,draft,archived',
            'options' => 'nullable|array',
            'options.*.name' => 'required_with:options|string',
            'options.*.values' => 'required_with:options|array',
            'variants' => 'nullable|array',
            'variants.*.option1' => 'required_with:variants|string',
            'variants.*.option2' => 'nullable|string',
            'variants.*.option3' => 'nullable|string',
            'variants.*.price' => 'required_with:variants|numeric',
            'variants.*.sku' => 'nullable|string',
            'variants.*.inventory_quantity' => 'nullable|integer',
        ]);

        try {
           
            $payload = [
                'product' => [
                    'title' => $validated['title'],
                    'body_html' => $validated['description'] ?? null,
                    'vendor' => $validated['vendor'],
                    'product_type' => $validated['product_type'] ?? null,
                    'status' => $validated['status'] ?? 'active',
                ],
            ];

            // Add options if present
            if (!empty($validated['options'])) {
                $payload['product']['options'] = $validated['options'];
            }

            // Add variants if present
            if (!empty($validated['variants'])) {
                $payload['product']['variants'] = $validated['variants'];
            }

            // Make the request to Shopify
            $response = $this->client->post('products.json', [
                'json' => $payload,
            ]);

            $responseBody = json_decode($response->getBody(), true);
            $statusCode = $responseBody->getStatusCode();

            if ($statusCode === 201 && isset($responseBody['product']['id'])) {
                $productId = $responseBody['product']['id'];
                Log::info("Product created successfully: " . $productId);
                $result = [
                    'status' => true,
                    'status_code' => $statusCode,
                    'shopify_product_id' => $productId,
                    'data' => $responseBody['product'] ?? [],
                ];
            } else {
                Log::info("Unexpected success status code: " . $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully in Shopify.',
                'data' => $responseBody,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create product in Shopify', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product in Shopify.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
