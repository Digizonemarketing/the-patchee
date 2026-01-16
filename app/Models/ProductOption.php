<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOption extends Model
{
    //protected $fillable = ['shopify_option_id', 'shopify_product_id', 'product_id', 'name', 'values'];
    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected $casts = [
        'values' => 'array',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
