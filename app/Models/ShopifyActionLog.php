<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Store;


class ShopifyActionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'type',
        'resource_id',
        'status',
        'message',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
