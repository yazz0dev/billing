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
        // This page is mostly JS driven. Pass minimal data if needed.
        // The JS will call API endpoints to check activation.
        $this->render('staff/mobile_scanner.html', [ // Or .php if you need PHP vars in it
            'pageTitle' => 'Mobile Barcode Scanner',
            // No layout needed for this simple HTML page:
        ], null); // null for layout to render raw HTML
    }

    // --- API Methods for Scanner ---

    public function apiActivateDesktopScanning(Request $request, Response $response)
    {
        if (!$this->authService->check()) {
            $response->json(['success' => false, 'message' => 'User not authenticated.'], 401); return;
        }
        $currentUser = $this->authService->user();
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
        $this->pairingRepo->deactivateSessionByDesktop($currentUser['id']);
        $this->notificationService->create("Mobile scanner deactivated by {$currentUser['username']}", 'info', $currentUser['id'], 3000, "Scanner Deactivated");
        $response->json(['success' => true, 'message' => 'Mobile scanner mode deactivated.']);
    }

    public function apiCheckDesktopActivation(Request $request, Response $response)
    {
        if (!$this->authService->check()) {
            $response->json(['success' => false, 'is_active' => false, 'message' => 'Desktop user not authenticated.'], 401); return;
        }
        $currentUser = $this->authService->user();
        $session = $this->pairingRepo->findActiveSessionForDesktop($currentUser['id']);
        if ($session) {
            $statusMessage = $session->status === 'mobile_active' ? 'Mobile scanner is connected.' : 'Waiting for mobile to connect.';
            $response->json(['success' => true, 'is_active' => true, 'status' => $session->status, 'staff_username' => $session->staff_username, 'message' => $statusMessage]);
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

        $updatedSession = $this->pairingRepo->activateMobileForSession($currentUser['id'], $currentMobileSessionId);

        if ($updatedSession) {
            $this->notificationService->create("Mobile scanner connected for {$updatedSession->staff_username}",'info', $currentUser['id']);
            $response->json(['success' => true, 'session_activated' => true, 'message' => 'Scanner session activated with POS.', 'staff_username' => $updatedSession->staff_username]);
        } else {
            // Check if already active with THIS mobile session
            $alreadyActiveWithThisMobile = $this->pairingRepo->findActiveSessionByMobile($currentUser['id'], $currentMobileSessionId);
            if ($alreadyActiveWithThisMobile) {
                $this->pairingRepo->updateMobileHeartbeat((string)$alreadyActiveWithThisMobile->_id);
                 $response->json(['success' => true, 'session_activated' => true, 'message' => 'Scanner session already active for this mobile.', 'staff_username' => $alreadyActiveWithThisMobile->staff_username]);
            } else {
                $response->json(['success' => false, 'session_activated' => false, 'message' => 'POS terminal has not activated scanner mode, or another mobile is paired.', 'current_user' => $currentUser['username']]);
            }
        }
    }

    public function apiSubmitScannedProduct(Request $request, Response $response)
    {
        if (!$this->authService->check()) {
            $response->json(['success' => false, 'message' => 'Mobile user not authenticated.'], 401); return;
        }
        $currentUser = $this->authService->user();
        $scannedProductIdOrBarcode = trim($request->json('scanned_product_id', $request->post('scanned_product_id')));
        $quantity = (int) $request->json('quantity', $request->post('quantity', 1));

        if (empty($scannedProductIdOrBarcode)) {
            $response->json(['success' => false, 'message' => 'Scanned product ID/barcode missing.'], 400); return;
        }

        $activePairing = $this->pairingRepo->findActiveSessionByMobile($currentUser['id'], session_id());
        if (!$activePairing) {
            $response->json(['success' => false, 'message' => 'No active pairing session. Please re-activate on POS.'], 403); return;
        }

        // Find product by ID or barcode/name
        $productDoc = $this->productRepo->findById($scannedProductIdOrBarcode) ?? $this->productRepo->findByNameOrBarcode($scannedProductIdOrBarcode);

        if (!$productDoc) {
            $response->json(['success' => false, 'message' => "Product '{$scannedProductIdOrBarcode}' not found."], 404); return;
        }
        // Check stock if necessary, though POS will do the final check
        if ($productDoc->stock < $quantity) {
             $response->json(['success' => false, 'message' => "Insufficient stock for '{$productDoc->name}'. Available: {$productDoc->stock}"], 400); return;
        }


        $itemData = [
            'product_id' => (string) $productDoc->_id,
            'product_name' => $productDoc->name,
            'price' => (float) $productDoc->price,
            'quantity' => $quantity,
        ];

        if ($this->pairingRepo->addScannedItemToSession((string)$activePairing->_id, $itemData)) {
            $this->notificationService->create("{$productDoc->name} scanned by {$currentUser['username']}", 'info', $currentUser['id']); // Notify self/POS
            $response->json(['success' => true, 'message' => 'Scan recorded.', 'product_name' => $productDoc->name]);
        } else {
            $response->json(['success' => false, 'message' => 'Failed to record scan.'], 500);
        }
    }

    public function apiGetScannedItemsForDesktop(Request $request, Response $response)
    {
        if (!$this->authService->check()) {
            $response->json(['success' => false, 'message' => 'Desktop user not authenticated.'], 401); return;
        }
        $currentUser = $this->authService->user();
        $activeSession = $this->pairingRepo->findActiveSessionForDesktop($currentUser['id']);

        if (!$activeSession || $activeSession->status !== 'mobile_active') {
            $response->json(['success' => true, 'items' => [], 'message' => 'No active mobile scanner confirmed or mobile not connected.']); return;
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
            if ($item->scanned_at instanceof \MongoDB\BSON\UTCDateTime) {
                $itemScannedAtTimes[] = $item->scanned_at;
            }
        }

        if (!empty($itemScannedAtTimes)) {
            $this->pairingRepo->markItemsAsProcessed((string)$activeSession->_id, $itemScannedAtTimes);
        }
        $response->json(['success' => true, 'items' => $itemsToReturn]);
    }
}
