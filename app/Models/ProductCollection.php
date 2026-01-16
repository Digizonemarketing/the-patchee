<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductCollection extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'title',
        'body_html',
        'image_url',
        'product_ids',
        'published',
        'shopify_collection_id'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
