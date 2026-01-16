<?php

namespace App\Services;

use App\Models\Store;
use GuzzleHttp\Client;
use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    protected $client;

    public function getClient($storeId)
    {
        try {
            if (!$this->client) {
                $store = Store::find($storeId);

                if (!$store) {
                    throw new Exception("Store with code {$storeId} not found.");
                }

                $shopifyDomain = $store->shop_domain;
                $accessToken = $store->getAccessTokenAttribute($store->access_token);

                $this->client = new Client([
                    'base_uri' => "https://{$shopifyDomain}/admin/api/2025-07/",
                    'headers' => [
                        'X-Shopify-Access-Token' => $accessToken,
                        'Content-Type' => 'application/json',
                    ],
                ]);
            }

            return $this->client;

        } catch (Exception $e) {
            Log::error('Unauthorized Access Token: ' . $e->getMessage());
            return null;
        }
    }

    public function makeRequestWithRetry($storeId, $client, $method, $url, $options = [], $name = '', $maxRetries = 3, $delay = 2)
    {
        $attempts = 0;
        $message = '';

        while ($attempts < $maxRetries) {
            try {
                return $client->$method($url, $options); // Successful request
            } catch (\Exception $e) {
                $attempts++;
                $message = $e->getMessage();
                // Log error attempt
                ShopifyLoggerService::log(
                    $storeId,
                    'shopify_api_retry_'.$name,
                    null,
                    'error',
                    "Attempt {$attempts} for {$method} {$url}",
                    ['error' => $e->getMessage()]
                );

                $statusCode = $e->getCode();

                if ($statusCode == 429) { // Rate-limiting
                    $retryAfter = 3; // Default
                    if (method_exists($e, 'getResponse') && $e->getResponse()) {
                        $headers = $e->getResponse()->getHeader('Retry-After');
                        if (!empty($headers) && is_numeric($headers[0])) {
                            $retryAfter = (int) $headers[0];
                        }
                    }

                    ShopifyLoggerService::log(
                        $storeId,
                        'shopify_api_retry_'.$name,
                        null,
                        'error',
                        "Rate limit exceeded. Retrying after {$retryAfter} seconds.",
                        []
                    );

                    sleep($retryAfter);
                    $delay = min($retryAfter * 2, 60); // Exponential backoff with max limit
                } elseif ($statusCode >= 500 && $statusCode < 600) { // Server errors
                    ShopifyLoggerService::log(
                        $storeId,
                        'shopify_api_retry',
                        null,
                        'warning',
                        "Server error ({$statusCode}). Retrying after {$delay} seconds.",
                        []
                    );

                    sleep($delay);
                    $delay = min($delay * 2, 60); // Exponential backoff with cap
                } else {
                    throw new \Exception("Non-retryable error for {$method} {$url}: {$message}");
                }
            }
        }

        throw new \Exception("Max retries reached for {$method} {$url}: {$message}");
    }
}

