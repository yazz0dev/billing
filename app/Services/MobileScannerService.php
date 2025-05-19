<?php
namespace App\Services;

use App\Models\PairingSession;
use App\Models\Product;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Carbon;

class MobileScannerService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function activateDesktopScanning(string $staffUserId, string $staffUsername, string $desktopSessionId): array
    {
        // Mark any existing active/pending sessions for this user as superseded
        PairingSession::where('staff_user_id', $staffUserId)
            ->whereIn('status', ['desktop_initiated_pairing', 'mobile_active'])
            ->update([
                'status' => 'superseded_by_desktop',
                'updated_at' => now(),
                'session_expires_at' => now() // Expire old sessions immediately
            ]);

        $session = PairingSession::create([
            'staff_user_id' => $staffUserId,
            'staff_username' => $staffUsername,
            'desktop_session_id' => $desktopSessionId,
            'status' => 'desktop_initiated_pairing',
            'session_expires_at' => now()->addHours(12),
            'last_desktop_heartbeat' => now(),
        ]);

        if ($session) {
            return ['success' => true, 'message' => 'Mobile scanner mode activated. Waiting for mobile.', 'staff_username' => $staffUsername];
        }
        return ['success' => false, 'message' => 'Failed to create activation session.'];
    }

    public function deactivateDesktopScanning(string $staffUserId): array
    {
        PairingSession::where('staff_user_id', $staffUserId)
            ->whereIn('status', ['desktop_initiated_pairing', 'mobile_active'])
            ->update([
                'status' => 'completed_by_desktop',
                'updated_at' => now(),
                'session_expires_at' => now()
            ]);
        // $staffUser = User::find($staffUserId);
        // if ($staffUser) {
        //     $this->notificationService->create("Mobile scanner deactivated by {$staffUser->username}", 'info', 'admin', 3000, "Scanner Deactivated");
        // }
        return ['success' => true, 'message' => 'Mobile scanner mode deactivated.'];
    }

    public function checkDesktopActivation(string $staffUserId): array
    {
        $session = PairingSession::where('staff_user_id', $staffUserId)
            ->whereIn('status', ['desktop_initiated_pairing', 'mobile_active'])
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($session) {
            $isMobileConnected = $session->status === 'mobile_active' &&
                                 $session->last_mobile_heartbeat &&
                                 Carbon::parse($session->last_mobile_heartbeat)->diffInSeconds(now()) < 45; // Increased timeout

            $statusMessage = $isMobileConnected
                ? "Mobile scanner connected for {$session->staff_username}."
                : "Scanner activated, waiting for mobile connection or mobile connection lost.";

            return [
                'success' => true, 'is_active' => true, 'status' => $session->status,
                'staff_username' => $session->staff_username, 'message' => $statusMessage,
                'mobile_connected' => $isMobileConnected
            ];
        }
        return ['success' => true, 'is_active' => false, 'message' => 'Scanner mode is not active.'];
    }

    public function activateMobileSession(string $staffUserId, string $mobileSessionId): array
    {
        $session = PairingSession::where('staff_user_id', $staffUserId)
            ->where('status', 'desktop_initiated_pairing')
            ->orderBy('created_at', 'desc') // Get the latest initiated session
            ->first();

        if ($session) {
            $session->update([
                'status' => 'mobile_active',
                'mobile_session_id' => $mobileSessionId,
                'last_mobile_heartbeat' => now(),
                'session_expires_at' => now()->addHours(12),
            ]);
            // $this->notificationService->create("Mobile scanner connected for {$session->staff_username}", 'info', 'admin', 3000, "Scanner Connected");
            return ['success' => true, 'session_activated' => true, 'message' => 'Scanner session activated with POS.', 'staff_username' => $session->staff_username];
        }

        // Check if already active with THIS mobile
        $alreadyActiveSession = PairingSession::where('staff_user_id', $staffUserId)
            ->where('mobile_session_id', $mobileSessionId)
            ->where('status', 'mobile_active')
            ->first();

        if ($alreadyActiveSession) {
            $alreadyActiveSession->update(['last_mobile_heartbeat' => now(), 'session_expires_at' => now()->addHours(12)]);
            return ['success' => true, 'session_activated' => true, 'message' => 'Scanner session already active for this mobile.', 'staff_username' => $alreadyActiveSession->staff_username];
        }
        
        $staffUser = User::find($staffUserId);
        $currentUserUsername = $staffUser ? $staffUser->username : 'User';
        return ['success' => false, 'session_activated' => false, 'message' => 'POS terminal has not activated scanner mode for your account, or another mobile is already paired.', 'current_user' => $currentUserUsername];
    }

    public function submitScannedProduct(string $staffUserId, string $mobileSessionId, string $scannedIdentifier, int $quantity = 1): array
    {
        $activePairing = PairingSession::where('staff_user_id', $staffUserId)
            ->where('mobile_session_id', $mobileSessionId)
            ->where('status', 'mobile_active')
            ->first();

        if (!$activePairing) {
            return ['success' => false, 'message' => 'No active pairing session. Please re-activate on POS.', 'code' => 403];
        }

        $product = Product::where('_id', $scannedIdentifier)
            ->orWhere('barcode', $scannedIdentifier)
            ->orWhere('name', $scannedIdentifier) // Less reliable for exact match
            ->first();

        if (!$product) {
            // $this->notificationService->create("Scanned item '{$scannedIdentifier}' not found.", 'warning', $activePairing->staff_user_id, 5000, "Scan Error");
            return ['success' => false, 'message' => "Product '{$scannedIdentifier}' not found.", 'code' => 404];
        }

        if ($product->stock < $quantity) {
            // $this->notificationService->create("Low stock for '{$product->name}'. Available: {$product->stock}.", 'warning', $activePairing->staff_user_id, 5000, "Stock Alert");
            return ['success' => false, 'message' => "Insufficient stock for '{$product->name}'. Available: {$product->stock}", 'code' => 400];
        }

        $itemData = [
            'product_id' => (string) $product->id,
            'product_name' => $product->name,
            'price' => (float) $product->price,
            'quantity' => $quantity,
            'scanned_at' => now()->toDateTimeString(), // Store as string or Carbon instance
            'processed_by_desktop' => false,
        ];
        
        $activePairing->push('scanned_items', $itemData); // Using push for array field
        $activePairing->last_mobile_heartbeat = now();
        $activePairing->session_expires_at = now()->addHours(12);
        $activePairing->save();

        // $this->notificationService->create("{$product->name} scanned by {$activePairing->staff_username}", 'info', $activePairing->staff_user_id, 3000, "Item Scanned");
        return ['success' => true, 'message' => 'Scan recorded.', 'product_name' => $product->name];
    }

    public function getScannedItemsForDesktop(string $staffUserId): array
    {
        $activeSession = PairingSession::where('staff_user_id', $staffUserId)
            ->whereIn('status', ['desktop_initiated_pairing', 'mobile_active'])
            ->orderBy('updated_at', 'desc')
            ->first();

        if (!$activeSession || $activeSession->status !== 'mobile_active') {
            $message = ($activeSession && $activeSession->status === 'desktop_initiated_pairing')
                ? 'Waiting for mobile scanner to connect.'
                : 'Mobile scanner mode is not active or session expired.';
            return ['success' => true, 'items' => [], 'message' => $message];
        }
        
        $activeSession->last_desktop_heartbeat = now();
        $activeSession->session_expires_at = now()->addHours(12);
        // Atomically fetch and mark items as processed
        // This is tricky without a direct MongoDB "findAndModify" for embedded arrays.
        // A simpler approach: fetch unprocessed, then update. Potential race condition if calls are very frequent.
        
        $itemsToReturn = [];
        $itemsToUpdateQuery = [];

        if (is_array($activeSession->scanned_items)) {
            foreach ($activeSession->scanned_items as $index => $item) {
                // Ensure $item is an array or object that has 'processed_by_desktop'
                if (is_array($item) && isset($item['processed_by_desktop']) && $item['processed_by_desktop'] === false) {
                    $itemsToReturn[] = $item;
                    // Prepare to mark this item as processed by its index
                    $itemsToUpdateQuery["scanned_items.{$index}.processed_by_desktop"] = true;
                } elseif (is_object($item) && isset($item->processed_by_desktop) && $item->processed_by_desktop === false) {
                     $itemsToReturn[] = (array)$item; // Convert object to array
                     $itemsToUpdateQuery["scanned_items.{$index}.processed_by_desktop"] = true;
                }
            }
        }


        if (!empty($itemsToUpdateQuery)) {
            $activeSession->scanned_items = collect($activeSession->scanned_items)->map(function ($item) {
                if (is_array($item) && isset($item['processed_by_desktop']) && $item['processed_by_desktop'] === false) {
                    $item['processed_by_desktop'] = true;
                } elseif (is_object($item) && isset($item->processed_by_desktop) && $item->processed_by_desktop === false) {
                     $item->processed_by_desktop = true;
                }
                return $item;
            })->all();
        }
        $activeSession->save();

        return ['success' => true, 'items' => $itemsToReturn];
    }
}