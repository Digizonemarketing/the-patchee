<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Models\ProductCollection;
use App\Services\ShopifyLoggerService;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use App\Services\ShopifyService;

class ShopifyCollectionController extends Controller
{
    protected $client;

    protected $request;

    protected $shopifyService;

    public function __construct(ShopifyService $shopifyService)
    {
        $this->shopifyService = $shopifyService;
    }

    /**
     * Create a collection with products and optional image.
     */
    public function createCollection(Request $request)
    {
        $storeId = $request->input('store_id');
        $client = $this->shopifyService->getClient($storeId);

        if (!$client) {
            return response()->json(['message' => 'Invalid API key or access token', 'code' => 401]);
        }

        $validated = $request->validate([
            'title' => 'required|string',
            'body_html' => 'nullable|string',
            'image_url' => 'nullable|url',
            'product_ids' => 'required|array',
            'product_ids.*' => 'numeric',
            'shopify_collection_id' => 'nullable|numeric',
        ]);

        ShopifyLoggerService::log($storeId, 'create_or_update_collection', null, 'start', 'Create or Update Collection Process', [
            'collection_payload' => $request->all()
        ]);

        $collectionData = [
            'title' => $validated['title'],
            'body_html' => $validated['body_html'] ?? '',
        ];
        if (!empty($validated['image_url'])) {
            $collectionData['image'] = ['src' => $validated['image_url']];
        }

        try {
            $collectionId = $validated['shopify_collection_id'] ?? null;
            $shopifyCollectionId = $this->handleCollection($storeId, $client, $collectionId, $collectionData);
            
            if (!$shopifyCollectionId) {
                throw new \Exception('Collection creation/update failed.');
            }

            $this->addProductsToCollection($storeId, $client, $shopifyCollectionId, implode(',', $validated['product_ids']));

            ProductCollection::updateOrCreate(
                ['shopify_collection_id' => $shopifyCollectionId],
                [
                    'store_id' => $storeId,
                    'title' => $collectionData['title'],
                    'body_html' => $collectionData['body_html'],
                    'image_url' => $validated['image_url'] ?? null,
                    'published' => true,
                ]
            );

            $message = $collectionId ? 'Collection updated successfully!' : 'Collection created successfully!';
            ShopifyLoggerService::log($storeId, 'create_or_update_collection', $shopifyCollectionId, 'success', $message, [
                'collection_data' => $collectionData,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'shopify_collection_id' => $shopifyCollectionId,
            ], 201);
        } catch (\Exception $e) {
            ShopifyLoggerService::log($storeId, 'create_or_update_collection', $collectionId ?? null, 'error', $e->getMessage(), ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function handleCollection($storeId, $client, $collectionId, array $collectionData)
    {
        try {
            if ($collectionId) {
                $response = $this->shopifyService->makeRequestWithRetry($storeId, $client, 'GET', "custom_collections/{$collectionId}.json");
                $collectionResponse = json_decode($response->getBody(), true);
                if (empty($collectionResponse['custom_collection'])) {
                    $collectionId = null;
                }
            }
        } catch (\Exception $e) {
            ShopifyLoggerService::log($storeId, 'validate_collection', $collectionId, 'failed', 'Shopify collection ID does not exist', ['error' => $e->getMessage()]);
            $collectionId = null;
        }

        try {
            if ($collectionId) {
                $response = $this->shopifyService->makeRequestWithRetry($storeId, $client, 'PUT', "custom_collections/{$collectionId}.json", [
                    'json' => ['custom_collection' => $collectionData],
                ]);
            } else {
                $response = $this->shopifyService->makeRequestWithRetry($storeId, $client, 'POST', 'custom_collections.json', [
                    'json' => ['custom_collection' => $collectionData],
                ]);
            }
            
            $collectionResponse = json_decode($response->getBody(), true);
            return $collectionResponse['custom_collection']['id'] ?? null;
        } catch (\Exception $e) {
            ShopifyLoggerService::log($storeId, 'create_or_update_collection', $collectionId, 'error', $e->getMessage(), ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @throws \Exception
     */
    private function addProductsToCollection($storeId, $client, $collectionId, $productIds)
    {
        // Initialize an empty array to hold all existing product IDs in the collection
        $existingProductIds = [];
        $nextPageInfo = null;  // Initially, there is no pagination

        // Paginate through the collection's products to fetch all product IDs
        do {
            // Prepare the query parameters, including limit and page_info for pagination
            $query = [
                'collection_id' => $collectionId,
                'limit' => 250,  // You can set this to 250 (max limit) if it's allowed
            ];

            // Add the page_info parameter if it's available
            if ($nextPageInfo) {
                $query['page_info'] = $nextPageInfo;
            }

            // Fetch products for the current page
            $response = $this->shopifyService->makeRequestWithRetry($storeId, $client, 'GET', 'collects.json', ['query' => $query]);

            // Decode the response body
            $existingCollects = json_decode($response->getBody(), true);

            // Extract product IDs from the current page of results
            $existingProductIds = array_merge($existingProductIds, array_map(function ($collect) {
                return $collect['product_id'];
            }, $existingCollects['collects']));

            // Reset nextPageInfo to null and check for the 'rel="next"' link in the response headers
            $nextPageInfo = null;

            // Check if there's a 'Link' header containing pagination information
            if (isset($response->getHeaders()['Link'])) {
                foreach ($response->getHeaders()['Link'] as $link) {
                    if (strpos($link, 'rel="next"') !== false) {
                        // Extract the page_info for the next page from the link
                        preg_match('/page_info=([^&]+)/', $link, $matches);
                        if (isset($matches[1])) {
                            $nextPageInfo = $matches[1]; // Set the page_info for the next request
                        }
                        break;
                    }
                }
            }

        } while ($nextPageInfo); // Continue until there are no more pages

        // Convert the requested product IDs into an array
        $requestedProductIds = explode(',', $productIds);

        // Products to add (only products that are not already in the collection)
        $productsToAdd = array_diff($requestedProductIds, $existingProductIds);

        // Products to remove (if any)
        $productsToRemove = array_diff($existingProductIds, $requestedProductIds);

        // Add new products to the collection
        foreach ($productsToAdd as $productId) {
            try {
                // Make a request to add the product to the collection
                $this->shopifyService->makeRequestWithRetry($storeId, $client, 'POST', 'collects.json', [
                    'json' => [
                        'collect' => [
                            'collection_id' => $collectionId,
                            'product_id' => $productId,
                        ],
                    ],
                ]);
            } catch (\Exception $e) {
                // Handle exception if the product already exists in the collection
                if (str_contains($e->getMessage(), 'already exists in this collection')) {
                    ShopifyLoggerService::log(
                        $storeId,
                        'create_or_update_collection',
                        $collectionId,
                        'info',
                        "Product {$productId} already exists in the collection, skipping.",
                        ['product_id' => $productId]
                    );
                }

                // Log and rethrow other exceptions
                ShopifyLoggerService::log(
                    $storeId,
                    'create_or_update_collection',
                    $collectionId,
                    'error',
                    "Product {$productId}",
                    ['error' => 'ProductID: '. $productId . '** '. $e->getMessage()]
                );
            }
        }

        // Remove products not in the request from the collection
        foreach ($productsToRemove as $productId) {
            $collect = array_filter($existingCollects['collects'], function ($collect) use ($productId) {
                return $collect['product_id'] === $productId;
            });

            if (!empty($collect)) {
                $collectId = array_values($collect)[0]['id'];
                $this->shopifyService->makeRequestWithRetry($storeId, $client, 'DELETE', "collects/{$collectId}.json");
            }
        }
    }

    private function makeRequestWithRetry($storeId, $client, $method, $url, $options = [], $maxRetries = 5, $delay = 2)
    {
        $attempts = 0;
        $response = null;

        while ($attempts < $maxRetries) {
            try {
                return $client->$method($url, $options); // If successful, return the response
            } catch (\Exception $e) {
                $attempts++;
                // Log the error with the attempt count
                ShopifyLoggerService::log(
                    $storeId,
                    'create_or_update_collection',
                    null,
                    'error',
                    "Attempt {$attempts}: {$url}",
                    ['error' => $e->getMessage()]
                );

                // Check for rate-limiting (HTTP 429)
                if ($e->getCode() == 429) {
                    // Get Retry-After header if available
                    $retryAfter = $e->getResponse()->getHeader('Retry-After');
                    $retryAfter = $retryAfter ? (int)$retryAfter[0] : $delay; // Default to the existing delay if no Retry-After header

                    // Log the delay time
                    ShopifyLoggerService::log(
                        $storeId,
                        'create_or_update_collection',
                        null,
                        'info',
                        "Rate limit exceeded. Retrying after {$retryAfter} seconds.",
                        []
                    );

                    // Delay before retrying
                    sleep($retryAfter);

                    // Exponential backoff: increase delay with each attempt
                    $delay = $retryAfter * 2; // You can tweak this multiplier as necessary
                } elseif ($e->getCode() >= 500 && $e->getCode() < 600) {
                    // Server errors, apply exponential backoff
                    sleep($delay);
                    $delay *= 2;
                } else {
                    // Break the loop for other errors
                    throw $e;
                }

                // If we've reached max retries, throw an exception
                if ($attempts >= $maxRetries) {
                    throw new \Exception("Max retries reached for {$url}: " . $e->getMessage());
                }
            }
        }

        return $response;
    }

    public function deleteCollection($collectionId, Request $request)
    {
        $storeId = $request->input('store_id');
        try {
            $client = $this->shopifyService->getClient($storeId);

            if ($client == null) {
                return response(['message' => 'Invalid API key or access token', 'code' => 401]);
            }

            // Find the collection in the database
            $collection = ProductCollection::where('shopify_collection_id', $collectionId)->first();

            if ($collection) {
                $collection->delete();
            }

            if ($collectionId) {
                // Delete the collection from Shopify with retry mechanism
                $this->shopifyService->makeRequestWithRetry($storeId, $client, 'DELETE', "custom_collections/{$collectionId}.json");
            }

            ShopifyLoggerService::log(
                $storeId,
                'delete_collection',
                $collectionId,
                'Completed',
                'Collection deleted successfully',
                ['collection_payload' => $collectionId]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Collection deleted successfully!',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Collection not found ' . $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Collection not found with ID: ' . $collectionId,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}