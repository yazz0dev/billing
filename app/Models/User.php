<?php

namespace App\Models;

use Jenssegers\Mongodb\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $connection = 'mongodb';
    // protected $collection = 'users'; // Default 'users' is fine

    protected $fillable = [
        'username',
        'email',
        'password',
        'role', // 'admin', 'staff'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }

    public function hasRole(string $role): bool
    {
        return strtolower($this->role) === strtolower($role);
    }

    public function hasAnyRole(array $roles): bool
    {
        if (empty($this->role)) {
            return false;
        }
        $userRoleLower = strtolower($this->role);
        foreach ($roles as $role) {
            if ($userRoleLower === strtolower($role)) {
                return true;
            }
        }
        return false;
    }
}