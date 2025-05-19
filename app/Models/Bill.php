<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class Bill extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'bills_new'; // Matches your old collection name

    protected $fillable = [
        'items', // Array of item objects/arrays
        'total_amount',
        'user_id', // Stored as string or ObjectId, Eloquent handles conversion
        'username', // Username of the staff who generated the bill
    ];

    protected $casts = [
        'items' => 'array',
        'total_amount' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationship (optional, if User model is set up for it)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}