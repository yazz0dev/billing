<?php // src/Notification/NotificationRepository.php
namespace App\Notification;

use App\Core\Database;
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class NotificationRepository
{
    private Collection $collection;

    public function __construct()
    {
        $this->collection = Database::connect()->selectCollection('popup_notifications');
    }

    public function save(string $message, string $type, $target, int $duration, ?string $title): ?string
    {
        $notification = [
            'message' => $message,
            'type' => $type,
            'target' => $target,
            'duration' => $duration,
            'title' => $title,
            'created_at' => new UTCDateTime(),
            'is_active' => true,
            'seen_by' => []
        ];
        $result = $this->collection->insertOne($notification);
        return (string) $result->getInsertedId();
    }

    public function findActiveForUser(string $userId, string $userRole): array
    {
        $query = [
            'is_active' => true,
            'seen_by' => ['$ne' => $userId],
            '$or' => [
                ['target' => 'all'],
                ['target' => $userId],
                ['target' => $userRole],
            ]
        ];
        $options = ['sort' => ['created_at' => -1], 'limit' => 15];
        return $this->collection->find($query, $options)->toArray();
    }

    public function markAsSeen(string $notificationId, string $userId): bool
    {
        try {
            $result = $this->collection->updateOne(
                ['_id' => new ObjectId($notificationId)],
                ['$addToSet' => ['seen_by' => $userId]]
            );
            return $result->getModifiedCount() > 0;
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            return false;
        }
    }
}
