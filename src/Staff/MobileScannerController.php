<?php // src/Staff/MobileScannerController.php
namespace App\Staff;

use App\Core\Controller; // For rendering the HTML page
use App\Core\Request;
use App\Core\Response;
use App\Auth\AuthService;
use App\Product\ProductRepository; // For product lookup on scan
use App\Notification\NotificationService;

class MobileScannerController extends Controller
{
    private PairingSessionRepository $pairingRepo;
    private AuthService $authService;
    private ProductRepository $productRepo;
    private NotificationService $notificationService;


    public function __construct()
    {
        parent::__construct(); // For page rendering
        $this->pairingRepo = new PairingSessionRepository();
        $this->authService = new AuthService();
        $this->productRepo = new ProductRepository();
        $this->notificationService = new NotificationService();
    }

    // Page rendering
    public function showScannerPage(Request $request, Response $response)
    {
        // Render the PHP template using the minimal layout
        // Pass the required JS files to the layout via $pageScripts
        $this->render('staff/mobile_scanner.php', [
            'pageTitle' => 'Mobile Barcode Scanner',
            // Pass the required JS files to the layout
            'pageScripts' => [
                 // html5-qrcode.min.js should be in your public/js directory
                 '/js/html5-qrcode.min.js', // Assuming this path
                 '/js/mobile-scanner.js'
            ],
             'bodyClass' => 'layout-minimal' // Ensure body padding is not added by main layout
        ], 'layouts/minimal.php'); // Use minimal layout for mobile page
    }

    // ... rest of the controller methods (apiActivateDesktopScanning, etc.) remain the same ...
     public function apiActivateDesktopScanning(Request $request, Response $response)
    {
        if (!$this->authService->check()) {
            $response->json(['success' => false, 'message' => 'User not authenticated.'], 401); return;
        }
        $currentUser = $this->authService->user();
        // Use current session ID as a unique identifier for the desktop instance
        $sessionId = $this->pairingRepo->createDesktopInitiatedSession($currentUser['id'], $currentUser['username'], session_id());

        if ($sessionId) {
            $response->json(['success' => true, 'message' => 'Mobile scanner mode activated. Waiting for mobile.', 'staff_username' => $currentUser['username']]);
        } else {
            $response->json(['success' => false, 'message' => 'Failed to create activation session.'], 500);
        }
    }

    public function apiDeactivateDesktopScanning(Request $request, Response $response)
    {
         if (!$this->authService->check()) {
            $response->json(['success' => false, 'message' => 'User not authenticated.'], 401); return;
        }
        $currentUser = $this->authService->user();
        // Deactivate the session associated with the current desktop user and session ID
        $this->pairingRepo->deactivateSessionByDesktop($currentUser['id']); // Modify repo method if needed to use desktop session ID
        $this->notificationService->create("Mobile scanner deactivated by {$currentUser['username']}", 'info', 'admin', 3000, "Scanner Deactivated"); // Notify admin
        $response->json(['success' => true, 'message' => 'Mobile scanner mode deactivated.']);
    }

     public function apiCheckDesktopActivation(Request $request, Response $response)
    {
        if (!$this->authService->check()) {
            $response->json(['success' => false, 'is_active' => false, 'message' => 'Desktop user not authenticated.'], 401); return;
        }
        $currentUser = $this->authService->user();
        // Find session by desktop user ID and desktop session ID
        $session = $this->pairingRepo->findActiveSessionForDesktop($currentUser['id']); // Modify repo method if needed to use desktop session ID
        if ($session) {
            $statusMessage = $session->status === 'mobile_active' ? 'Mobile scanner is connected.' : 'Waiting for mobile to connect.';
             // Check mobile heartbeat
             $lastHeartbeat = $session->last_mobile_heartbeat ? $session->last_mobile_heartbeat->toDateTime() : null;
             $isMobileConnected = $session->status === 'mobile_active' && $lastHeartbeat && (time() - $lastHeartbeat->getTimestamp()) < 30; // Assume heartbeat every ~7s
             $statusMessage = $isMobileConnected ? "Mobile scanner connected for {$session->staff_username}." : "Scanner activated, waiting for mobile connection or mobile connection lost.";


            $response->json([
                'success' => true,
                'is_active' => true,
                'status' => $session->status,
                'staff_username' => $session->staff_username,
                'message' => $statusMessage,
                 'mobile_connected' => $isMobileConnected // Indicate if mobile heartbeat is recent
             ]);
        } else {
            $response->json(['success' => true, 'is_active' => false, 'message' => 'Scanner mode is not active.']);
        }
    }


    public function apiActivateMobileSession(Request $request, Response $response)
    {
        // Mobile device is logged in and calls this
        if (!$this->authService->check()) {
            $response->json(['success' => false, 'session_activated' => false, 'message' => 'Mobile user not authenticated.'], 401); return;
        }
        $currentUser = $this->authService->user();
        $currentMobileSessionId = session_id();

        // Attempt to activate a desktop-initiated session matching the user ID
        $updatedSession = $this->pairingRepo->activateMobileForSession($currentUser['id'], $currentMobileSessionId);

        if ($updatedSession) {
            $this->notificationService->create("Mobile scanner connected for {$currentUser['username']}",'info', 'admin', 3000, "Scanner Connected"); // Notify admin
            $response->json(['success' => true, 'session_activated' => true, 'message' => 'Scanner session activated with POS.', 'staff_username' => $updatedSession->staff_username]);
        } else {
            // Check if already active with THIS mobile session
            $alreadyActiveWithThisMobile = $this->pairingRepo->findActiveSessionByMobile($currentUser['id'], $currentMobileSessionId);
             $desktopActiveButPending = $this->pairingRepo->findActiveSessionForDesktop($currentUser['id']); // Check if desktop is active but pending
            
            if ($alreadyActiveWithThisMobile) {
                 // Already active, just update heartbeat
                 $this->pairingRepo->updateMobileHeartbeat((string)$alreadyActiveWithThisMobile->_id);
                 $response->json(['success' => true, 'session_activated' => true, 'message' => 'Scanner session already active for this mobile.', 'staff_username' => $alreadyActiveWithThisMobile->staff_username]);
            } elseif ($desktopActiveButPending && $desktopActiveButPending->status === 'desktop_initiated_pairing') {
                 // Desktop is active but mobile hasn't successfully paired yet (maybe incorrect mobile session ID or other issue)
                 $response->json(['success' => false, 'session_activated' => false, 'message' => 'Failed to pair. Ensure the POS activated scanner mode for your account and try again.', 'current_user' => $currentUser['username']]);
            }
            else {
                 // No matching desktop-initiated session found for this user ID
                $response->json(['success' => false, 'session_activated' => false, 'message' => 'POS terminal has not activated scanner mode for your account, or another mobile is already paired.', 'current_user' => $currentUser['username']]);
            }
        }
    }

    public function apiSubmitScannedProduct(Request $request, Response $response)
    {
        if (!$this->authService->check()) {
            $response->json(['success' => false, 'message' => 'Mobile user not authenticated.'], 401); return;
        }
        $currentUser = $this->authService->user();
        
        // Check JSON first, then fall back to POST
        $scannedProductIdOrBarcode = $request->json('scanned_product_id');
        if ($scannedProductIdOrBarcode === null) {
            $scannedProductIdOrBarcode = $request->post('scanned_product_id');
        }
        
        $quantity = (int) $request->json('quantity');
        if ($quantity <= 0) {
            $quantity = (int) $request->post('quantity', 1);
            if ($quantity <= 0) $quantity = 1;
        }

        $scannedProductIdOrBarcode = trim($scannedProductIdOrBarcode ?? '');
        
        if (empty($scannedProductIdOrBarcode)) {
            $response->json(['success' => false, 'message' => 'Scanned product ID/barcode missing.'], 400); return;
        }

        // Find the active session using the mobile user ID and session ID
        $activePairing = $this->pairingRepo->findActiveSessionByMobile($currentUser['id'], session_id());
        if (!$activePairing) {
            $response->json(['success' => false, 'message' => 'No active pairing session found for this mobile device. Please re-activate on POS.', 'code' => 'no_active_pairing'], 403); return; // Added error code for JS to handle
        }

        // Find product by ID or barcode/name
        $productDoc = $this->productRepo->findById($scannedProductIdOrBarcode);
        if (!$productDoc) {
             // If not found by ID, try by name or barcode field if you have one
             $productDoc = $this->productRepo->findByNameOrBarcode($scannedProductIdOrBarcode);
        }


        if (!$productDoc) {
            $this->notificationService->create("Scanned item '{$scannedProductIdOrBarcode}' not found in products.", 'warning', 'admin', 5000, "Scan Error"); // Notify admin
            $response->json(['success' => false, 'message' => "Product '{$scannedProductIdOrBarcode}' not found."], 404); return;
        }
        // Check stock if necessary, though POS will do the final check during billing
        // Adding stock check here provides quicker feedback to the mobile user
        if ($productDoc->stock < $quantity) {
             $this->notificationService->create("Low stock for '{$productDoc->name}'. Available: {$productDoc->stock}.", 'warning', 'admin', 5000, "Stock Alert"); // Notify admin
             $response->json(['success' => false, 'message' => "Insufficient stock for '{$productDoc->name}'. Available: {$productDoc->stock}"], 400); return;
        }


        $itemData = [
            'product_id' => (string) $productDoc->_id,
            'product_name' => $productDoc->name,
            'price' => (float) $productDoc->price,
            'quantity' => $quantity,
        ];

        if ($this->pairingRepo->addScannedItemToSession((string)$activePairing->_id, $itemData)) {
             // Notify the desktop user paired with this mobile session
             $this->notificationService->create("{$productDoc->name} scanned by {$currentUser['username']}", 'info', (string)$activePairing->staff_user_id, 3000, "Item Scanned");
            $response->json(['success' => true, 'message' => 'Scan recorded.', 'product_name' => $productDoc->name]);
        } else {
             $this->notificationService->create("Failed to record scan for '{$productDoc->name}'.", 'error', 'admin', 5000, "Scan Processing Error"); // Notify admin
            $response->json(['success' => false, 'message' => 'Failed to record scan.'], 500);
        }
    }

    public function apiGetScannedItemsForDesktop(Request $request, Response $response)
    {
        if (!$this->authService->check()) {
            $response->json(['success' => false, 'message' => 'Desktop user not authenticated.'], 401); return;
        }
        $currentUser = $this->authService->user();
        // Find the active session by desktop user ID and desktop session ID
        $activeSession = $this->pairingRepo->findActiveSessionForDesktop($currentUser['id']); // Modify repo method if needed to use desktop session ID

        if (!$activeSession || $activeSession->status !== 'mobile_active') {
            // If desktop initiated but mobile hasn't connected, return empty but maybe a different message
             if ($activeSession && $activeSession->status === 'desktop_initiated_pairing') {
                 $response->json(['success' => true, 'items' => [], 'message' => 'Waiting for mobile scanner to connect.']);
             } else {
                 $response->json(['success' => true, 'items' => [], 'message' => 'Mobile scanner mode is not active.']);
             }
            return;
        }

        $items = $this->pairingRepo->getUnprocessedScannedItems((string)$activeSession->_id);
        $itemScannedAtTimes = [];
        $itemsToReturn = [];

        foreach ($items as $item) {
            $itemsToReturn[] = [ // Convert BSON to simple array for JS
                'product_id' => (string) $item->product_id,
                'product_name' => $item->product_name,
                'price' => $item->price,
                'quantity' => $item->quantity,
            ];
            // Collect the scanned_at timestamps for marking as processed
            if (isset($item->scanned_at) && $item->scanned_at instanceof \MongoDB\BSON\UTCDateTime) {
                 // Ensure we collect the *original* BSON UTCDateTime objects or their exact values
                 // For marking, the timestamp is the most reliable field within the embedded item
                $itemScannedAtTimes[] = $item->scanned_at;
            } else {
                // Fallback if scanned_at is missing or not a UTCDateTime (shouldn't happen if saved correctly)
                error_log("Scanned item missing or invalid 'scanned_at' field: " . json_encode($item));
            }
        }

        if (!empty($itemScannedAtTimes)) {
            // Mark items as processed based on their `scanned_at` timestamps within the session document
             $this->pairingRepo->markItemsAsProcessed((string)$activeSession->_id, $itemScannedAtTimes);
        }
        
        // Also update desktop heartbeat to prevent session expiry if desktop is active
        $this->pairingRepo->updateDesktopHeartbeat((string)$activeSession->_id);


        $response->json(['success' => true, 'items' => $itemsToReturn]);
    }
    
    // Add a method to update desktop heartbeat in PairingSessionRepository
     // PairingSessionRepository.php needs updateDesktopHeartbeat method
}