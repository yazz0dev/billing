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
        $hasExpiryIndex = false; // For TTL index
        $hasMobileIndex = false; // For finding by mobile session
        $hasDesktopIndex = false; // For finding by desktop session


        foreach ($indexes as $index) {
             // Compound index for staff_user_id and status (useful for finding active sessions)
            if (isset($index->key['staff_user_id']) && isset($index->key['status']) && count($index->key) === 2) {
                $hasStaffStatusIndex = true;
            }
            // TTL index for session expiry
            if (isset($index->key['session_expires_at']) && isset($index->expireAfterSeconds) && $index->expireAfterSeconds === 0) {
                $hasExpiryIndex = true;
            }
             // Index for finding by mobile session
             if (isset($index->key['mobile_session_id']) && count($index->key) === 1) {
                 $hasMobileIndex = true;
             }
             // Index for finding by desktop session
             if (isset($index->key['desktop_session_id']) && count($index->key) === 1) {
                 $hasDesktopIndex = true;
             }
        }

        if (!$hasStaffStatusIndex) {
            $this->collection->createIndex(['staff_user_id' => 1, 'status' => 1]);
        }
        if (!$hasExpiryIndex) {
            // TTL index on session_expires_at field
            // Documents expire after 'session_expires_at' date is reached
            $this->collection->createIndex(['session_expires_at' => 1], ['expireAfterSeconds' => 0]);
        }
         if (!$hasMobileIndex) {
             $this->collection->createIndex(['mobile_session_id' => 1]);
         }
         if (!$hasDesktopIndex) {
             $this->collection->createIndex(['desktop_session_id' => 1]);
         }
    }

    public function createDesktopInitiatedSession(string $staffUserId, string $staffUsername, string $desktopSessionId): ?string
    {
        try {
            $objectId = new ObjectId($staffUserId);
            // Mark any existing active/pending sessions for this user as superseded
            $this->collection->updateMany(
                ['staff_user_id' => $objectId, 'status' => ['$in' => ['desktop_initiated_pairing', 'mobile_active']]],
                ['$set' => ['status' => 'superseded_by_desktop', 'updated_at' => new UTCDateTime()]]
            );

            $session = [
                'staff_user_id' => $objectId,
                'staff_username' => $staffUsername,
                'desktop_session_id' => $desktopSessionId,
                'mobile_session_id' => null, // Will be set when mobile connects
                'status' => 'desktop_initiated_pairing', // pending mobile connection
                'scanned_items' => [],
                'created_at' => new UTCDateTime(),
                'updated_at' => new UTCDateTime(),
                // Extend expiry whenever desktop activates or mobile heartbeats/scans
                'session_expires_at' => new UTCDateTime((time() + 12 * 3600) * 1000), // Example: Expires in 12 hours of inactivity
                'last_mobile_heartbeat' => null,
                'last_desktop_heartbeat' => new UTCDateTime(), // Track desktop activity too
            ];
            $result = $this->collection->insertOne($session);
            return (string) $result->getInsertedId();
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
             error_log("PairingSessionRepository createDesktopInitiatedSession InvalidArgumentException: " . $e->getMessage());
             return null; // Handle invalid user ID format
        } catch (\Throwable $e) {
             error_log("PairingSessionRepository createDesktopInitiatedSession Error: " . $e->getMessage());
             return null; // Generic error
        }
    }

     public function findActiveSessionForDesktop(string $staffUserId): ?object
    {
        try {
             $objectId = new ObjectId($staffUserId);
             // Find active sessions for this user ID (desktop_initiated or mobile_active)
            // Filter by status and sort by creation/update time to get the latest active one
            return $this->collection->findOne(
                ['staff_user_id' => $objectId, 'status' => ['$in' => ['desktop_initiated_pairing', 'mobile_active']]],
                ['sort' => ['updated_at' => -1]] // Get the most recent active session
            );
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
             return null; // Handle invalid user ID format
        }
    }


    public function activateMobileForSession(string $staffUserId, string $mobileSessionId): ?object // Returns updated session doc
    {
        try {
             $objectId = new ObjectId($staffUserId);
             // Find a desktop-initiated session for this user that *isn't* already marked as mobile active
             // Ensure we find the correct one if there are multiple superseded sessions
            return $this->collection->findOneAndUpdate(
                [
                    'staff_user_id' => $objectId,
                    'status' => 'desktop_initiated_pairing',
                    // Optional: Ensure mobile_session_id is null or matches for idempotency
                    // 'mobile_session_id' => null
                ],
                ['$set' => [
                    'status' => 'mobile_active',
                    'mobile_session_id' => $mobileSessionId,
                    'last_mobile_heartbeat' => new UTCDateTime(),
                    'updated_at' => new UTCDateTime(),
                    'session_expires_at' => new UTCDateTime((time() + 12 * 3600) * 1000), // Extend expiry
                ]],
                ['returnDocument' => FindOneAndUpdate::AFTER, 'sort' => ['created_at' => -1]] // Get the latest initiated session
            );
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
             error_log("PairingSessionRepository activateMobileForSession InvalidArgumentException: " . $e->getMessage());
             return null;
        } catch (\Throwable $e) {
             error_log("PairingSessionRepository activateMobileForSession Error: " . $e->getMessage());
             return null;
        }
    }
    
    public function findActiveSessionByMobile(string $staffUserId, string $mobileSessionId): ?object
    {
        try {
            $objectId = new ObjectId($staffUserId);
            // Find the session linked to this specific mobile session ID AND user ID, and is active
            return $this->collection->findOne([
                'staff_user_id' => $objectId,
                'mobile_session_id' => $mobileSessionId,
                'status' => 'mobile_active'
            ]);
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
             return null;
        }
    }

    public function updateMobileHeartbeat(string $sessionId): bool
    {
        try {
            $objectId = new ObjectId($sessionId);
            $result = $this->collection->updateOne(
                ['_id' => $objectId],
                ['$set' => [
                    'last_mobile_heartbeat' => new UTCDateTime(),
                    'updated_at' => new UTCDateTime(),
                    'session_expires_at' => new UTCDateTime((time() + 12 * 3600) * 1000), // Extend expiry
                ]]
            );
            return $result->getModifiedCount() > 0;
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            return false;
        }
    }
    
    // New method to update desktop heartbeat
    public function updateDesktopHeartbeat(string $sessionId): bool
    {
        try {
            $objectId = new ObjectId($sessionId);
             // Only update if the session is active (either state)
            $result = $this->collection->updateOne(
                ['_id' => $objectId, 'status' => ['$in' => ['desktop_initiated_pairing', 'mobile_active']]],
                ['$set' => [
                    'last_desktop_heartbeat' => new UTCDateTime(),
                    'updated_at' => new UTCDateTime(),
                    'session_expires_at' => new UTCDateTime((time() + 12 * 3600) * 1000), // Extend expiry
                ]]
            );
            return $result->getModifiedCount() > 0;
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            return false;
        }
    }


    public function addScannedItemToSession(string $sessionId, array $itemData): bool
    {
        // itemData: ['product_id' => string, 'product_name' => string, 'price' => float, 'quantity' => int]
        try {
            $objectId = new ObjectId($sessionId);
            $item = [
                'product_id' => new ObjectId($itemData['product_id']), // Store as ObjectId
                'product_name' => $itemData['product_name'],
                'price' => (float) $itemData['price'],
                'quantity' => (int) $itemData['quantity'],
                'scanned_at' => new UTCDateTime(),
                'processed_by_desktop' => false, // New flag
            ];
            $result = $this->collection->updateOne(
                ['_id' => $objectId, 'status' => 'mobile_active'], // Only add items to active sessions
                ['$push' => ['scanned_items' => $item], '$set' => ['updated_at' => new UTCDateTime(), 'last_mobile_heartbeat' => new UTCDateTime()]] // Update timestamp and heartbeat
            );
            return $result->getModifiedCount() > 0;
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
             error_log("PairingSessionRepository addScannedItemToSession InvalidArgumentException: " . $e->getMessage());
            return false;
        }
    }

    public function getUnprocessedScannedItems(string $sessionId): array
    {
        try {
            $objectId = new ObjectId($sessionId);
            $session = $this->collection->findOne(['_id' => $objectId]);
            if (!$session || !isset($session->scanned_items) || !is_array($session->scanned_items->getArrayCopy())) {
                return [];
            }
            $unprocessed = [];
            foreach ($session->scanned_items as $item) {
                // Check the flag. Also filter out items without the flag for backward compatibility if needed.
                if (isset($item->processed_by_desktop) && $item->processed_by_desktop === false) {
                    $unprocessed[] = $item;
                }
            }
            return $unprocessed;
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
             return [];
        }
    }

     public function markItemsAsProcessed(string $sessionId, array $itemScannedAtTimes): bool // Array of UTCDateTime objects
    {
        if (empty($itemScannedAtTimes)) return true; // Nothing to mark

        try {
            $objectId = new ObjectId($sessionId);
            // Use $addToSet with $each to add multiple timestamps to the 'processed_timestamps' array
            // This is safer than $set on the embedded array as it avoids race conditions if items are added concurrently
            // Alternative: Iterate and update each item by its unique properties (like scanned_at) - this is complex.
            // Let's stick to marking by timestamp array for simplicity, assuming timestamps are unique enough.
            // A more robust approach would be to give each scanned item a unique ID when added.

            // MongoDB does not have a direct way to update embedded documents by matching multiple criteria efficiently
            // without iterating or using aggregation pipeline updates (complex).
            // The `$addToSet` approach below is for *tracking* which timestamps HAVE been processed, not *setting* the flag on the item itself.
            // To set the flag on the item, you typically need `arrayFilters` in `updateMany` for MongoDB >= 3.6.

            // Let's revise: Instead of collecting timestamps, just set the flag on *all* unprocessed items for the given session ID in one go.
            // This is simpler and achieves the goal of marking them processed after they are fetched.

            $result = $this->collection->updateOne(
                ['_id' => $objectId],
                 ['$set' => ['scanned_items.$[elem].processed_by_desktop' => true, 'updated_at' => new UTCDateTime()]],
                 [
                     'arrayFilters' => [['elem.processed_by_desktop' => false]],
                      // Only update if the session is still active when marking
                     'filter' => ['status' => 'mobile_active'] 
                 ]
            );
             // This update will mark ALL unprocessed items as processed.
             // The desktop polling logic needs to fetch *then* mark.
            return $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0;

        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
             error_log("PairingSessionRepository markItemsAsProcessed InvalidArgumentException: " . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
             error_log("PairingSessionRepository markItemsAsProcessed Error: " . $e->getMessage());
             return false;
        }
    }


    public function deactivateSessionByDesktop(string $staffUserId): bool
    {
        try {
            $objectId = new ObjectId($staffUserId);
            // Find the *active* session for this user (desktop_initiated or mobile_active)
            // and mark it as completed by desktop.
            $result = $this->collection->updateMany(
                ['staff_user_id' => $objectId, 'status' => ['$in' => ['desktop_initiated_pairing', 'mobile_active']]],
                ['$set' => ['status' => 'completed_by_desktop', 'updated_at' => new UTCDateTime(), 'session_expires_at' => new UTCDateTime()]] // Expire immediately
            );
            return $result->getModifiedCount() > 0;
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            return false;
        }
    }
}