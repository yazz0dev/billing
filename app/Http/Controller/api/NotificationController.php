<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function fetchNotifications(Request $request)
    {
        $user = Auth::user();
        try {
            $notifications = $this->notificationService->getNotificationsForUser($user->id, $user->role);
            return response()->json(['status' => 'success', 'data' => $notifications]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Could not fetch notifications: ' . $e->getMessage()], 500);
        }
    }

    public function markSeen(Request $request)
    {
        $validatedData = $request->validate([
            'notification_id' => 'required|string',
        ]);
        $user = Auth::user();
        $success = $this->notificationService->markNotificationAsSeen($validatedData['notification_id'], $user->id);

        if ($success) {
            return response()->json(['status' => 'success', 'message' => 'Notification marked as seen.']);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Failed to mark as seen or already seen.'], 400);
        }
    }
}