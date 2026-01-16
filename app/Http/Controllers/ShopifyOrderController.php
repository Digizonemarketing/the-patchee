<?php

namespace App\Http\Controllers;

use App\Services\ShopifyOrderService;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Store;
use App\Models\Setting;
use App\Services\ShopifyLoggerService;
use Illuminate\Support\Facades\Log;
use App\Services\ErpOrderService;

class ShopifyOrderController extends Controller
{
    protected $shopifyOrderService;
    protected $erpOrderService;
    protected $shopifyService;

    
    public function __construct(ShopifyOrderService $shopifyOrderService, ErpOrderService $erpOrderService, ShopifyService $shopifyService)
    {
        $this->shopifyOrderService = $shopifyOrderService;
        $this->erpOrderService = $erpOrderService;
        $this->shopifyService = $shopifyService;
    }

    
    public function fetchOrders(Request $request)
    {
        if (!$this->isFromAdmin($request)) {
            abort(404); 
        }
        ShopifyLoggerService::log(
            'shopify_fetch_order_button',
            null,
            'processing',
            'Process Started',
            null
        );
        $result = $this->shopifyOrderService->fetchOrdersFromShopify();

        return $result;
        

    }

    private function isFromAdmin(Request $request)
    {
        
        $referer = $request->headers->get('referer');
        return strpos($referer, 'admin/orders') !== false;
    }

    public function handleOrderCreateWebhook(Request $request)
    {   
        if (Setting::where('key', 'create_order_webhook')->value('value')) {
            $storeCode = $request->query('store_code');
            
            try {
                $store = Store::findByStoreCode($storeCode);

                if (!$store) {
                    return response(['message' => "Store with code {$storeCode} not found.", 'code' => 404]);
                }

                $storeId = $store->id;

                $client = $this->shopifyService->getClient($storeId);
                if ($client == null) {
                    return response(['message' => 'Invalid API key or access token', 'code' => 401]);
                }
                

                //$allowedDomains = array_map('trim', explode(',', env('SHOPIFY_ALLOWED_DOMAINS', '')));
                $shopifyDomain = $request->header('X-Shopify-Shop-Domain');
                Log::info('Order Webhook: Triggered from Domain: '.$shopifyDomain);
                /*if (!in_array($shopifyDomain, $allowedDomains)) {
                    Log::warning('Unauthorized Shopify domain: ' . $shopifyDomain);
                    return response(['message' => 'Unauthorized domain', 'code'=> 401]);
                }*/
                $orderId = $request->input('id'); 

                $existingOrder = Order::where('order_id', $orderId)
                    ->where('store_id', $storeId)
                    ->first();

                if ($existingOrder) {
                    Log::info("Order {$orderId} from store {$storeCode} has already been processed.");
                    return response(['message' => 'Order already processed', 'code'=> 200]);
                }
                
                ShopifyLoggerService::log(
                    $storeId,
                    'order_create_webhook',
                    null,
                    'Triggered',
                    'Shopify Order Crate Webook Triggered',
                    null
                );

                $orderData = json_decode($request->getContent(), true);
                $shopifyOrderId = $orderData['id'];

                ShopifyLoggerService::log(
                    $storeId,
                    'order_create_webhook',
                    $shopifyOrderId,
                    'processing',
                    'Shopify Order Create Request Payload.',
                    ['order_request' => $orderData]
                );

                Log::info('Order Webhook: Request', ['response' => $orderData]);


                if (!$orderData) {
                    Log::error('Order Webhook: Invalid handleOrderCreateWebhook payload');
                    return response(['message' => 'Bad Request', 'code'=> 400]);
                }

                $orderCreated = $this->shopifyOrderService->createOrder($orderData, $storeId);
                $erpStatus = $this->erpOrderService->pushOrderToERP($orderCreated, $storeId);

                
                if (!$erpStatus) {
                    Log::error('Order Webhook: Failed to Create Order in ERP!');
                    return response(['message' => 'Order failed to create in ERP', 'code'=> 201]);
                }

                Log::info('Order Webhook: Shopify handleOrderCreateWebhook processed successfully', ['order_id' => $shopifyOrderId]);
                
                return response(['message' => 'Order successfully pushed to ERP', 'status' => 200]);

            } catch (\Exception $e) {
                Log::error('Order Webhook: Shopify Order Webhook Error', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } else {
            return response(['message' => 'Order Webhook is disabled', 'status' => 201]);
        }
    }
}
