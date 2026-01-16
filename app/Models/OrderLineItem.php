<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Order;

class OrderLineItem extends Model
{
    protected $fillable = [
        'order_id',
        'variant_id',
        'product_id',
        'name',
        'sku',
        'quantity',
        'price',
        'total_price'
    ];

    public function Order()
    {
        return $this->belongsTo(Order::class);
    }
}

