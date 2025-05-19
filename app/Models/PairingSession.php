<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class PairingSession extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'pairing_sessions';

    protected $fillable = [
        'staff_user_id',
        'staff_username',
        'desktop_session_id',
        'mobile_session_id',
        'status', // 'desktop_initiated_pairing', 'mobile_active', 'completed_by_desktop', 'superseded_by_desktop', 'expired'
        'scanned_items', // Array of scanned item data
        'session_expires_at',
        'last_mobile_heartbeat',
        'last_desktop_heartbeat',
    ];

    protected $casts = [
        'scanned_items' => 'array',
        'session_expires_at' => 'datetime',
        'last_mobile_heartbeat' => 'datetime',
        'last_desktop_heartbeat' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // You might want to add a TTL index on 'session_expires_at' in your MongoDB deployment
    // db.pairing_sessions.createIndex( { "session_expires_at": 1 }, { expireAfterSeconds: 0 } )

    public function staffUser()
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}