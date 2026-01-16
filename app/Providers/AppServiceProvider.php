<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyCsrfToken::except([
            'shopify-order-create-webhook',
            'shopify/webhooks/create-order',
            'shopify/product/*',
            'shopify/collections',
            '/shopify/product/discounts',
            '/shopify/product/discounts/*',
            'shopify/collections/*'
        ]);
    }
}
