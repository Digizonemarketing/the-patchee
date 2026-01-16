<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use App\Services\ShopifyService;
use App\Models\OrderLineItem;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class ShopifyOrderService
{

    protected $shopifyService;

    public function __construct(ShopifyService $shopifyService)
    {
        $this->shopifyService = $shopifyService;
    }

    
    public function fetchOrdersFromShopify()
    {
        try {

            $createdAtMin = Carbon::now()->subHours(1)->toDateTimeString();

            $response = $this->shopifyService->getClient()->get('orders.json', [
                'query' => [
                    'status' => 'any',  // Filter orders by status
                    //'financial_status' => 'pending',
                   // 'created_at_min' => $createdAtMin,
                    'limit' => 250
                ],
            ]);
            // Decode the JSON response
            $orders = json_decode($response->getBody()->getContents(), true)['orders'];
            

            if ($response->getStatusCode() === 200 && is_array($orders)) {

                // Loop through the orders and save them to the database
                foreach ($orders as $order) {
                    $save = $this->saveOrder($order);
                    
                }

                // Return success notification
                Notification::make()
                    ->title('Orders fetched and saved successfully!')
                    ->success()
                    ->send();
            } else {
                // Return failure notification
                Notification::make()
                    ->title('Failed to fetch orders from Shopify.')
                    ->danger()
                    ->send();
            }

        } catch (\Exception $e) {
            
            \Log::error('Shopify Order Fetch Error: ' . $e->getMessage());
            Notification::make()
                ->title('Failed to fetch orders.')
                ->danger()
                ->send();
        }

        return redirect('/admin/orders');
    }

    public function saveOrder($order) {
        try {
            Log::info('Create Order in DB');
            $shopifyOrderCreatedAt = isset($order['created_at']) ? $order['created_at'] : Carbon::now()->format('Y-m-d H:i:s');
            $orderCreatedAt = Carbon::parse($shopifyOrderCreatedAt)->format('Y-m-d H:i:s');
            // Prepare line items by selecting specific columns
            $lineItems = array_map(function ($item) {
                return [
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'sku' => $item['sku'],
                    'total_discount' => $item['total_discount'],
                ];
            }, $order['line_items']);

            $data = [
                'order_number' => isset($order['order_number']) ? $order['order_number'] : null,
                'payment_method' => isset($order['payment_gateway_names'][0]) ? $order['payment_gateway_names'][0] : null,
                'financial_status' => isset($order['financial_status']) ? $order['financial_status'] : null,
                'fulfillment_status' => isset($order['fulfillment_status']) ? $order['fulfillment_status'] : null,
                'total_discounts' => isset($order['total_discounts']) ? $order['total_discounts'] : null,
                'total_shipping_amount' => isset($order['total_shipping_price_set']['shop_money']['amount']) ? $order['total_shipping_price_set']['shop_money']['amount'] : null,
                'total_shipping_currency' => isset($order['total_shipping_price_set']['shop_money']['currency_code']) ? $order['total_shipping_price_set']['shop_money']['currency_code'] : null,
                'current_subtotal_price' => isset($order['current_subtotal_price']) ? $order['current_subtotal_price'] : null,
                'order_name' => isset($order['name']) ? $order['name'] : null,
                'contact_email' => isset($order['contact_email']) ? $order['contact_email'] : null,
                'order_created_at' => $orderCreatedAt,

                // Shipping Address Individual Fields
                'shipping_zip' => isset($order['shipping_address']['zip']) ? $order['shipping_address']['zip'] : null,
                'shipping_country' => isset($order['shipping_address']['country']) ? $order['shipping_address']['country'] : null,
                'shipping_country_code' => isset($order['shipping_address']['country_code']) ? $order['shipping_address']['country_code'] : null,
                'shipping_city' => isset($order['shipping_address']['city']) ? $order['shipping_address']['city'] : null,
                'shipping_address1' => isset($order['shipping_address']['address1']) ? $this->sanitizeAddress($order['shipping_address']['address1']) : null,
                'shipping_address2' => isset($order['shipping_address']['address2']) ? $this->sanitizeAddress($order['shipping_address']['address2']) : null,
                'shipping_latitude' => isset($order['shipping_address']['latitude']) ? $order['shipping_address']['latitude'] : null,
                'shipping_longitude' => isset($order['shipping_address']['longitude']) ? $order['shipping_address']['longitude'] : null,
                'shipping_name' => isset($order['shipping_address']['name']) ? $order['shipping_address']['name'] : null,
                'shipping_first_name' => isset($order['shipping_address']['first_name']) ? $order['shipping_address']['first_name'] : null,
                'shipping_last_name' => isset($order['shipping_address']['last_name']) ? $order['shipping_address']['last_name'] : null,
                'shipping_phone' => isset($order['shipping_address']['phone']) ? $order['shipping_address']['phone'] : null,
                
                // JSON Columns
                'shipping_address' => isset($order['shipping_address']) ? $this->sanitizeAddress(json_encode($order['shipping_address'])) : null,
                'line_items' => isset($lineItems) ? json_encode($lineItems) : null,
            ];
           
            // Save order to ShopifyOrders table
            $shopifyOrder = Order::updateOrCreate(
                ['order_id' => $order['id']],
                $data
            );
            
            // Save each order's line items to the order_items table
               
            foreach ($order['line_items'] as $item) {
                $shopifyOrder->orderItems()->updateOrCreate(
                    [   
                        'order_id' => $shopifyOrder->id,
                        'variant_id' => isset($item['variant_id']) ? $item['variant_id'] : null,
                        'product_id' => isset($item['product_id']) ? $item['product_id'] : null,
                        'sku' => isset($item['sku']) ? $item['sku'] : null,
                        'name' => isset($item['name']) ? $item['name'] : null,
                        'quantity' => isset($item['quantity']) ? $item['quantity'] : null,
                        'price' => isset($item['price']) ? $item['price'] : null,
                    ]
                );
            }

            ShopifyLoggerService::log(
                'shopify_fetch_order_button',
                $order['id'],
                'Success',
                'Order Created in DB',
                ['order_create' => $data]
            );

            Log::info('Order Created in DB');
            
        } catch (\Exception $e) {
             // Log the error
            Log::error('Create Order in DB Failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ShopifyLoggerService::log(
                'shopify_fetch_order_button',
                $order['id'],
                'Failed',
                'Order Failed to Create in DB: '. $e->getMessage(),
                ['order_create' => $data]
            );
        }
    }

    public function sanitizeAddress($address) {
        $sanitizedAddress = str_replace('\"', '', $address); // Remove escaped quotes
        $sanitizedAddress = trim($sanitizedAddress, '"');    // Remove outer quotes
        $sanitizedAddress = preg_replace('/\s+"/', ' ', $sanitizedAddress); // Remove extra inner quotes
        $sanitizedAddress = preg_replace('/"\s+/', ' ', $sanitizedAddress); // Remove extra inner quotes
        return $sanitizedAddress;
    }

    public function createOrder($order, $storeId) {

        try {
            Log::info('Create Order in DB');
            $shopifyOrderCreatedAt = isset($order['created_at']) ? $order['created_at'] : Carbon::now()->format('Y-m-d H:i:s');
            $orderCreatedAt = Carbon::parse($shopifyOrderCreatedAt)->format('Y-m-d H:i:s');

            // Prepare line items by selecting specific columns
            $lineItems = array_map(function ($item) {
                return [
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'sku' => $item['sku'],
                    'total_discount' => $item['total_discount'],
                ];
            }, $order['line_items']);

            $data = [
                'store_id' => $storeId,
                'order_number' => isset($order['order_number']) ? $order['order_number'] : null,
                'payment_method' => isset($order['payment_gateway_names'][0]) ? $order['payment_gateway_names'][0] : null,
                'financial_status' => isset($order['financial_status']) ? $order['financial_status'] : null,
                'fulfillment_status' => isset($order['fulfillment_status']) ? $order['fulfillment_status'] : null,
                'total_discounts' => isset($order['total_discounts']) ? $order['total_discounts'] : null,
                'total_shipping_amount' => isset($order['total_shipping_price_set']['shop_money']['amount']) ? $order['total_shipping_price_set']['shop_money']['amount'] : null,
                'total_shipping_currency' => isset($order['total_shipping_price_set']['shop_money']['currency_code']) ? $order['total_shipping_price_set']['shop_money']['currency_code'] : null,
                'current_subtotal_price' => isset($order['current_subtotal_price']) ? $order['current_subtotal_price'] : null,
                'order_name' => isset($order['name']) ? $order['name'] : null,
                'contact_email' => isset($order['contact_email']) ? $order['contact_email'] : null,
                'order_created_at' => $orderCreatedAt,

                // Shipping Address Individual Fields
                'shipping_zip' => isset($order['shipping_address']['zip']) ? $order['shipping_address']['zip'] : null,
                'shipping_country' => isset($order['shipping_address']['country']) ? $order['shipping_address']['country'] : null,
                'shipping_country_code' => isset($order['shipping_address']['country_code']) ? $order['shipping_address']['country_code'] : null,
                'shipping_city' => isset($order['shipping_address']['city']) ? $order['shipping_address']['city'] : null,
                'shipping_address1' => isset($order['shipping_address']['address1']) ? $this->sanitizeAddress($order['shipping_address']['address1']) : null,
                'shipping_address2' => isset($order['shipping_address']['address2']) ? $this->sanitizeAddress($order['shipping_address']['address2']) : null,
                'shipping_latitude' => isset($order['shipping_address']['latitude']) ? $order['shipping_address']['latitude'] : null,
                'shipping_longitude' => isset($order['shipping_address']['longitude']) ? $order['shipping_address']['longitude'] : null,
                'shipping_name' => isset($order['shipping_address']['name']) ? $order['shipping_address']['name'] : null,
                'shipping_first_name' => isset($order['shipping_address']['first_name']) ? $order['shipping_address']['first_name'] : null,
                'shipping_last_name' => isset($order['shipping_address']['last_name']) ? $order['shipping_address']['last_name'] : null,
                'shipping_phone' => isset($order['shipping_address']['phone']) ? $order['shipping_address']['phone'] : null,
                // JSON Columns
                'shipping_address' => isset($order['shipping_address']) ? $this->sanitizeAddress(json_encode($order['shipping_address'])) : null,
                'line_items' => isset($lineItems) ? json_encode($lineItems) : null,
            ];
            
            // Save order to ShopifyOrders table
            $shopifyOrder = Order::updateOrCreate(
                ['order_id' => $order['id']],
                $data
            );
            
            // Save each order's line items to the order_items table
               
            foreach ($order['line_items'] as $item) {
                $shopifyOrder->orderItems()->updateOrCreate(
                    [   
                        'store_id' => $storeId,
                        'order_id' => $shopifyOrder->id,
                        'variant_id' => isset($item['variant_id']) ? $item['variant_id'] : null,
                        'product_id' => isset($item['product_id']) ? $item['product_id'] : null,
                        'sku' => isset($item['sku']) ? $item['sku'] : null,
                        'name' => isset($item['name']) ? $item['name'] : null,
                        'quantity' => isset($item['quantity']) ? $item['quantity'] : null,
                        'price' => isset($item['price']) ? $item['price'] : null,
                    ]
                );
            }

            Log::info('Order Created in DB');

            ShopifyLoggerService::log(
                $storeId,
                'order_create_webhook',
                $order['id'],
                'Success',
                'Order Created in DB',
                ['order_create' => $data]
            );

            return $shopifyOrder;
            
        } catch (\Exception $e) {
            // Log the error
            Log::error('Create Order in DB Failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ShopifyLoggerService::log(
                $storeId,
                'order_create_webhook',
                $order['id'],
                'Failed',
                'Failed to create Order in DB',
                ['error' => $e->getMessage()]
            );
        }

        return false;
    }
}

