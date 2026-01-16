<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Store extends Model
{
    protected $fillable = [
        'store_name',
        'store_code',
        'store_url',
        'shop_domain',
        'access_token',
        'erp_backend_url',
        'erp_api_key'
    ];

    /**
     * Encrypt the access token before saving it to the database.
     *
     * @param string $value
     */
    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = Crypt::encrypt($value);
    }

    /**
     * Decrypt the access token when retrieving it from the database.
     *
     * @param string $value
     * @return string
     */
    public function getAccessTokenAttribute($value): string
    {
        return Crypt::decrypt($value);
    }

    /**
     * Find a store by store code and decrypt sensitive data.
     *
     * @param string $storeCode
     * @return Store|null
     */
    public static function findByStoreCode(string $storeCode): ?self
    {   
        
        $store = self::where('store_code', $storeCode)->first();

        if ($store) {
            $store->access_token = Crypt::decrypt($store->access_token);
        }

        return $store;
    }

    public function productCollections()
    {
        return $this->hasMany(ProductCollection::class);
    }

    public function productDiscounts()
    {
        return $this->hasMany(ProductDiscount::class);
    }

    public function shopifyActionLogs()
    {
        return $this->hasMany(ShopifyActionLog::class);
    }
}
