<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    public function create(string $message, string $type = 'info', $target = 'all', int $duration = 5000, ?string $title = null): ?Notification
    {
        if (empty(trim($message))) {
            throw new \InvalidArgumentException("Notification message cannot be empty.");
        }
        return Notification::create([
            'message' => $message,
            'type' => $type,
            'target' => $target, // 'all', 'admin', 'staff', or user_id string
            'duration' => $duration,
            'title' => $title,
        ]);
    }

    public function getNotificationsForUser(string $userId, string $userRole)
    {
        // Fetch notifications that are active, not yet seen by the user, and targeted to them.
        return Notification::where('is_active', true)
            ->where(function ($query) use ($userId, $userRole) {
                $query->where('target', 'all')
                      ->orWhere('target', $userId)
                      ->orWhere('target', $userRole);
            })
            ->whereRaw(['seen_by' => ['$ne' => $userId]]) // MongoDB specific query for "not in array"
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();
    }

    public function markNotificationAsSeen(string $notificationId, string $userId): bool
    {
        $notification = Notification::find($notificationId);
        if ($notification) {
            // AddToSet equivalent for MongoDB
            $notification->push('seen_by', $userId, true); // true for unique
            return true;
        }
        return false;
    }
}