<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Store;
use Illuminate\Http\Request;

class ValidateStoreCode
{
    public function handle(Request $request, Closure $next)
    {
        $storeCode = $request->header('X-Store-Code') ?? $request->input('store_code');

        if (!$storeCode) {
            return response()->json(['error' => 'Store code is required.'], 400);
        }

        $store = Store::where('store_code', $storeCode)->first();

        if (!$store) {
            return response()->json(['error' => 'Invalid store code.'], 404);
        }

        $accessToken = $store->getAccessTokenAttribute($store->access_token);

        if ($request->header('X-Shopify-Access-Token') !== $accessToken) {
            return response()->json(['error' => 'Unauthorized Token.'], 404);
        }

        $request->merge(['store_id' => $store->id]);

        return $next($request);
    }
}
