<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    //protected $fillable = ['shopify_variant_id', 'shopify_product_id', 'product_id', 'title', 'price', 'sku', 'inventory_quantity', 'weight', 'weight_unit'];
    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
