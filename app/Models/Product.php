<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class Product extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'products';

    protected $fillable = [
        'name',
        'price',
        'stock',
        'low_stock_threshold',
        'barcode',
        // Add other fields from your old system if necessary
    ];

    protected $casts = [
        'price' => 'float',
        'stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}