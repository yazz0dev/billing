<?php // src/Notification/NotificationService.php
namespace App\Notification;

class NotificationService
{
    private NotificationRepository $repository;

    public function __construct()
    {
        $this->repository = new NotificationRepository();
    }

    public function create(string $message, string $type = 'info', $target = 'all', int $duration = 5000, ?string $title = null): ?string
    {
        // Add any business logic/validation before saving
        if (empty(trim($message))) {
            throw new \InvalidArgumentException("Notification message cannot be empty.");
        }
        // Only persist notifications for specific targets (admin, staff, or individual user IDs)
        // 'all' might be too broad for persistence depending on your strategy.
        // For this example, we'll persist 'all' but the JS poller targets roles/IDs.
        // Or, adjust `findActiveForUser` to be more selective if 'all' is too noisy.
        
        return $this->repository->save($message, $type, $target, $duration, $title);
    }

    public function getNotificationsForUser(string $userId, string $userRole): array
    {
        $docs = $this->repository->findActiveForUser($userId, $userRole);
        return array_map(fn($doc) => (array) $doc->getArrayCopy(), $docs);
    }

    public function markNotificationAsSeen(string $notificationId, string $userId): bool
    {
        return $this->repository->markAsSeen($notificationId, $userId);
    }
}
