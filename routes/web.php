<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyOrderController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ShopifyProductController;
use App\Http\Controllers\ShopifyProductDiscountController;
use App\Http\Controllers\ShopifyCollectionController;
Route::get('/', function () {
    abort(404);
});

Route::get('/login', function () {
    abort(404);
});


Route::post('/shopify-order-create-webhook', [ShopifyOrderController::class, 'handleOrderCreateWebhook'])->name('shopify.order.create.webhook');
Route::post('/shopify/webhooks/create-order', [ShopifyOrderController::class, 'handleOrderCreateWebhook'])->name('shopify.order.create.webhook');

/*Route::prefix('shopify/{storeCode}')->group(function () {
    Route::post('/product/image/replace', [ProductImageController::class, 'replaceProductImages']);
    Route::post('/product', [ShopifyProductController::class, 'createProduct']);
    Route::post('/collections', [ShopifyCollectionController::class, 'createCollection']);
    Route::delete('/collections/{id}', [ShopifyCollectionController::class, 'deleteCollection']);
    Route::post('/product/discounts', [ShopifyProductDiscountController::class, 'applyDiscounts']);
    Route::post('/order-create-webhook', [ShopifyOrderController::class, 'handleOrderCreateWebhook'])
        ->name('shopify.order.create.webhook');
});*/

Route::group(['prefix' => 'shopify',  'middleware' => 'validate.store'], function()
{
    Route::post('/product/image/replace', [ProductImageController::class, 'replaceProductImages']);
    Route::post('/product', [ShopifyProductController::class, 'createProduct']);
    Route::post('/collections', [ShopifyCollectionController::class, 'createCollection']);
    Route::delete('/collections/{id}', [ShopifyCollectionController::class, 'deleteCollection']);
    Route::post('/product/discounts', [ShopifyProductDiscountController::class, 'applyDiscounts']);
});




