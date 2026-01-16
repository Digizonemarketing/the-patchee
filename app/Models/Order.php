<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\OrderLineItem;

class Order extends Model
{
    /*protected $fillable = [
        'shop_id',
        'order_id',
    ];*/

    protected $guarded = [];

    // Order items relationship
    public function orderItems()
    {
        return $this->hasMany(OrderLineItem::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}

