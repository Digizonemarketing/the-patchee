<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'shopify_product_id',
        'shopify_variant_id',
        'sku',
        'original_price',
        'discounted_price',
        'start_date',
        'end_date',
        'status',
        'shopify_synced',
        'is_reverted'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
