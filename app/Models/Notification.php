<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class Notification extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'popup_notifications'; // Matches your old collection name

    protected $fillable = [
        'message',
        'type', // 'info', 'success', 'warning', 'error'
        'target', // 'all', 'admin', 'staff', or a specific user ID (string)
        'duration', // Milliseconds, 0 for persistent
        'title',
        'is_active',
        'seen_by', // Array of user IDs who have seen it
    ];

    protected $casts = [
        'duration' => 'integer',
        'is_active' => 'boolean',
        'seen_by' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!isset($model->is_active)) {
                $model->is_active = true;
            }
            if (!isset($model->seen_by)) {
                $model->seen_by = [];
            }
        });
    }
}