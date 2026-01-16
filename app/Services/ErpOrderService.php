<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Store;
use Carbon\Carbon;
use App\Models\Setting;
use App\Models\OrderLineItem;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use App\Services\ShopifyLoggerService;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class ErpOrderService
{   
    public function sanitizeAddress($address) {
        $sanitizedAddress = str_replace('\"', '', $address); // Remove escaped quotes
        $sanitizedAddress = trim($sanitizedAddress, '"');    // Remove outer quotes
        $sanitizedAddress = preg_replace('/\s+"/', ' ', $sanitizedAddress); // Remove extra inner quotes
        $sanitizedAddress = preg_replace('/"\s+/', ' ', $sanitizedAddress); // Remove extra inner quotes
        return $sanitizedAddress;
    }

    public function pushOrderToERP(Order $order, $storeId) {
        Log::info('erp_order_create Order Request', ['response' => $order]);
        if (Setting::where('key', 'erp_create_order_enabled')->value('value')) {
            try {
                $store = Store::find($storeId);

                if (!$store) {
                    throw new \Exception("Store with code {$storeId} not found.");
                }

                $result = false;
                $erpRes = [];
                $erpApiUrl = $store->erp_backend_url . '/api/order/create'; 
                
                $client = new Client();
                $orderId = $order->order_id;
                $phone = ($order->shipping_phone) ? $order->shipping_phone : 123456789;
                
                $data = [
                    'total_discounts' => $order->total_discounts, 
                    'total_shipping_price_set' => [
                        'shop_money' => [
                            'amount' => $order->total_shipping_amount, 
                            'currency_code' => $order->total_shipping_currency,
                        ]
                    ],
                    'current_subtotal_price' => $order->current_subtotal_price,
                    'name' => $order->order_name, 
                    'line_items' => json_decode($order->line_items, true), 
                    'shipping_address' => [
                        'zip' => $order->shipping_zip, 
                        'country' => $order->shipping_country, 
                        'country_code' => $order->shipping_country_code, 
                        'city' => $order->shipping_city, 
                        'address2' => $this->sanitizeAddress($order->shipping_address2), 
                        'phone' => $phone, 
                        'address1' => $this->sanitizeAddress($order->shipping_address1),
                        'latitude' => $order->shipping_latitude, 
                        'name' => $order->shipping_name, 
                        'last_name' => $order->shipping_first_name, 
                        'first_name' => $order->shipping_last_name,
                        'longitude' => $order->shipping_longitude, 
                    ],
                    'financial_status' => $order->financial_status,
                    'contact_email' => $order->contact_email,
                ];
                
                Log::info('ERP API Request', ['response' => $data]);
                
                ShopifyLoggerService::log(
                    $storeId,
                    'erp_order_create',
                    $order->order_id,
                    'processing',
                    'ERP Order create Payload.',
                    ['erp_order_create_payload' => $data]
                );
                
                $response = $client->post($erpApiUrl, [
                    'json' => $data,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ]
                ]);

                
                $responseBody = json_decode($response->getBody(), true);

                Log::info('API Response', ['response' => $responseBody]);
                if (is_array($responseBody) && 
                    isset($responseBody['desc'], $responseBody['resultCode']) &&
                    $responseBody['desc'] === 'Success' &&
                    $responseBody['resultCode'] === '000000') {
                    $order->erp_synced = true;
                    $order->save();
                    $result = true;
                    Log::info('API Status: Success');
                    $erpRes = [
                        'order_status' => 'Success',
                        'order_desc' => $responseBody['desc'],
                        'order_result_code' => ($responseBody['resultCode']) ? $responseBody['resultCode'] : 'null',
                        'order_code' => isset($responseBody['entity']['orderCode']) && $responseBody['entity']['orderCode'] ? $responseBody['entity']['orderCode'] : 'null'

                    ];
                   
                    ShopifyLoggerService::log(
                        $storeId,
                        'erp_order_create',
                        $order->order_id,
                        'success',
                        'ERP Order created successfully',
                        ['erp_response' => $responseBody]
                    );
                } else {
                    $desc = is_array($responseBody) && isset($responseBody['desc']) ? $responseBody['desc'] : 'N/A';
                    $resultCode = is_array($responseBody) && isset($responseBody['resultCode']) ? $responseBody['resultCode'] : 'N/A';
                    $orderCode = is_array($responseBody) && isset($responseBody['entity']['orderCode']) ? $responseBody['entity']['orderCode'] : 'N/A';

                    $erpRes = [
                        'order_status' => 'Failed',
                        'order_desc' => $desc,
                        'order_result_code' => $resultCode,
                        'order_code' => $orderCode
                    ];

                    ShopifyLoggerService::log(
                        $storeId,
                        'erp_order_create',
                        $order->order_id,
                        'Failled',
                        'Failed to create order in ERP',
                        ['erp_response' => $responseBody]
                    );
                }
                
                return $result;
            } catch (\Exception $e) {
                Log::error('API Request Failed', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                ShopifyLoggerService::log(
                    $storeId,
                    'erp_order_create',
                    $order->order_id,
                    'Failed',
                    'Failed to create order in ERP',
                    ['erp_exception' => $e->getMessage()]
                );
                return false;
            }
        }
    }
  
}

