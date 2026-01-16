<?php

namespace App\Services;

use App\Models\ShopifyActionLog;

class ShopifyLoggerService
{
    public static function log($storeId, $type, $resourceId, $status, $message, $payload = null)
    {
        if ($payload) {
            $payload = json_encode($payload);
        }
        return ShopifyActionLog::create([
            'store_id' => $storeId,
            'type' => $type,
            'resource_id' => $resourceId,
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
        ]);
    }
}
