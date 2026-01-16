<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Validator;   

class Product extends Model
{
    //protected $fillable = ['shopify_product_id', 'title', 'description', 'price', 'extra_data'];
    protected $guarded = [];

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function options()
    {
        return $this->hasMany(ProductOption::class);
    }

    public function validate()
    {   
        try {
            $validator = Validator::make($this->toArray(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'vendor' => 'required|string|max:100',
                'product_type' => 'nullable|string|max:100',
                'status' => 'required|in:active,draft,archived',
                'options' => 'nullable|array',
                'options.*.name' => 'required_with:options|string',
                'options.*.values' => 'required_with:options|array',
                'variants' => 'nullable|array',
                'variants.*.option1' => 'required_with:variants|string',
                'variants.*.option2' => 'nullable|string',
                'variants.*.option3' => 'nullable|string',
                'variants.*.price' => 'required_with:variants|numeric',
                'variants.*.sku' => 'nullable|string',
                'variants.*.inventory_quantity' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }

            \Log::info('Validation success:', [
                'success' => true,
                'message' => 'Product data is valid.',
            ]);

        } catch (ValidationException $e) {
            \Log::error('Validation failed:', [
                'errors' => $e->errors(),
                'failedRules' => $e->validator->failed(),
            ]);
        }
    }
}
