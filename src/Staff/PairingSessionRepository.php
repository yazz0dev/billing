<?php // src/Staff/PairingSessionRepository.php
namespace App\Staff;

use App\Core\Database;
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Operation\FindOneAndUpdate; // For findOneAndUpdate options

class PairingSessionRepository
{
    private Collection $collection;

    public function __construct()
    {
        $this->collection = Database::connect()->selectCollection('pairing_sessions');
        // Ensure indexes (ideally run once during setup, or check and create here)
        $this->ensureIndexes();
    }

    private function ensureIndexes()
    {
        $indexes = iterator_to_array($this->collection->listIndexes());
        $hasStaffStatusIndex = false;
        $hasExpiryIndex = false;

        foreach ($indexes as $index) {
            if (isset($index->key['staff_user_id']) && isset($index->key['status'])) {
                $hasStaffStatusIndex = true;
            }
            if (isset($index->key['session_expires_at'])) {
                $hasExpiryIndex = true;
            }
        }

        if (!$hasStaffStatusIndex) {
            $this->collection->createIndex(['staff_user_id' => 1, 'status' => 1]);
        }
        if (!$hasExpiryIndex) {
            $this->collection->createIndex(['session_expires_at' => 1], ['expireAfterSeconds' => 0]);
        }
    }

    public function createDesktopInitiatedSession(string $staffUserId, string $staffUsername, string $desktopSessionId): ?string
    {
        // Mark any existing active/pending sessions for this user as superseded
        $this->collection->updateMany(
            ['staff_user_id' => new ObjectId($staffUserId), 'status' => ['$in' => ['desktop_initiated_pairing', 'mobile_active']]],
            ['$set' => ['status' => 'superseded_by_desktop', 'updated_at' => new UTCDateTime()]]
        );

        $session = [
            'staff_user_id' => new ObjectId($staffUserId),
            'staff_username' => $staffUsername,
            'desktop_session_id' => $desktopSessionId,
            'mobile_session_id' => null,
            'status' => 'desktop_initiated_pairing', // pending mobile connection
            'scanned_items' => [],
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
            'session_expires_at' => new UTCDateTime((time() + 8 * 3600) * 1000), // Expires in 8 hours
            'last_mobile_heartbeat' => null,
        ];
        $result = $this->collection->insertOne($session);
        return (string) $result->getInsertedId();
    }

    public function findActiveSessionForDesktop(string $staffUserId): ?object
    {
        return $this->collection->findOne([
            'staff_user_id' => new ObjectId($staffUserId),
            'status' => ['$in' => ['desktop_initiated_pairing', 'mobile_active']]
        ]);
    }

    public function activateMobileForSession(string $staffUserId, string $mobileSessionId): ?object // Returns updated session doc
    {
        return $this->collection->findOneAndUpdate(
            ['staff_user_id' => new ObjectId($staffUserId), 'status' => 'desktop_initiated_pairing'],
            ['$set' => [
                'status' => 'mobile_active',
                'mobile_session_id' => $mobileSessionId,
                'last_mobile_heartbeat' => new UTCDateTime(),
                'updated_at' => new UTCDateTime()
            ]],
            ['returnDocument' => FindOneAndUpdate::AFTER]
        );
    }
    
    public function findActiveSessionByMobile(string $staffUserId, string $mobileSessionId): ?object
    {
        return $this->collection->findOne([
            'staff_user_id' => new ObjectId($staffUserId),
            'mobile_session_id' => $mobileSessionId,
            'status' => 'mobile_active'
        ]);
    }

    public function updateMobileHeartbeat(string $sessionId): bool
    {
        $result = $this->collection->updateOne(
            ['_id' => new ObjectId($sessionId)],
            ['$set' => ['last_mobile_heartbeat' => new UTCDateTime(), 'updated_at' => new UTCDateTime()]]
        );
        return $result->getModifiedCount() > 0;
    }


    public function addScannedItemToSession(string $sessionId, array $itemData): bool
    {
        // itemData: ['product_id' => string, 'product_name' => string, 'price' => float, 'quantity' => int]
        $item = [
            'product_id' => new ObjectId($itemData['product_id']),
            'product_name' => $itemData['product_name'],
            'price' => (float) $itemData['price'],
            'quantity' => (int) $itemData['quantity'],
            'scanned_at' => new UTCDateTime(),
            'processed_by_desktop' => false, // New flag
        ];
        $result = $this->collection->updateOne(
            ['_id' => new ObjectId($sessionId)],
            ['$push' => ['scanned_items' => $item], '$set' => ['updated_at' => new UTCDateTime()]]
        );
        return $result->getModifiedCount() > 0;
    }

    public function getUnprocessedScannedItems(string $sessionId): array
    {
        $session = $this->collection->findOne(['_id' => new ObjectId($sessionId)]);
        if (!$session || !isset($session->scanned_items) || !is_array($session->scanned_items->getArrayCopy())) {
            return [];
        }
        $unprocessed = [];
        foreach ($session->scanned_items as $item) {
            if (isset($item->processed_by_desktop) && $item->processed_by_desktop === false) {
                $unprocessed[] = $item;
            }
        }
        return $unprocessed;
    }

    public function markItemsAsProcessed(string $sessionId, array $itemScannedAtTimes): bool // Array of UTCDateTime objects
    {
        if (empty($itemScannedAtTimes)) return true;

        $result = $this->collection->updateMany(
            ['_id' => new ObjectId($sessionId), 'scanned_items.scanned_at' => ['$in' => $itemScannedAtTimes]],
            ['$set' => ['scanned_items.$.processed_by_desktop' => true, 'updated_at' => new UTCDateTime()]]
        );
        return $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0; // Modified or if all were already processed
    }

    public function deactivateSessionByDesktop(string $staffUserId): bool
    {
        $result = $this->collection->updateMany(
            ['staff_user_id' => new ObjectId($staffUserId), 'status' => ['$in' => ['desktop_initiated_pairing', 'mobile_active']]],
            ['$set' => ['status' => 'completed_by_desktop', 'updated_at' => new UTCDateTime()]]
        );
        return $result->getModifiedCount() > 0;
    }
}
