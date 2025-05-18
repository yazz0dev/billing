<?php // src/Notification/NotificationController.php
namespace App\Notification;

use App\Core\Controller; // Base controller for things like CSRF if needed, but API might not use it.
use App\Core\Request;
use App\Core\Response;
use App\Auth\AuthService; // To get current user

class NotificationController // Does not extend Controller as it's pure API
{
    private NotificationService $notificationService;
    private AuthService $authService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
        $this->authService = new AuthService(); // To get user context
    }

    public function apiFetch(Request $request, Response $response)
    {
        if (!$this->authService->check()) {
            $response->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }
        $currentUser = $this->authService->user();
        $notifications = $this->notificationService->getNotificationsForUser($currentUser['id'], $currentUser['role']);
        $response->json(['status' => 'success', 'data' => $notifications]);
    }

    public function apiMarkSeen(Request $request, Response $response)
    {
        if (!$this->authService->check()) {
            $response->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }
        
        $notificationId = $request->json('notification_id', $request->post('notification_id'));
        if (empty($notificationId)) {
            $response->json(['status' => 'error', 'message' => 'Notification ID is required.'], 400);
            return;
        }

        $currentUser = $this->authService->user();
        $success = $this->notificationService->markNotificationAsSeen($notificationId, $currentUser['id']);

        if ($success) {
            $response->json(['status' => 'success', 'message' => 'Notification marked as seen.']);
        } else {
            $response->json(['status' => 'error', 'message' => 'Failed to mark notification as seen or already seen.'], 400);
        }
    }
}
