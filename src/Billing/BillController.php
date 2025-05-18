<?php // src/Billing/BillController.php

namespace App\Billing;

use App\Core\Request;
use App\Core\Response;
use App\Auth\AuthService; // To get current user

class BillController
{
    private BillService $billService;
    private AuthService $authService;

    public function __construct()
    {
        $this->billService = new BillService();
        $this->authService = new AuthService();
    }

    public function apiGenerateBill(Request $request, Response $response)
    {
        if (!$this->authService->check()) {
            $response->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $cartItems = $request->json('items', $request->post('items')); // Expects an array of items
        if (!is_array($cartItems) || empty($cartItems)) {
            $response->json(['success' => false, 'message' => 'No items provided for the bill.'], 400);
            return;
        }

        $currentUser = $this->authService->user();
        $result = $this->billService->generateBill($cartItems, $currentUser['id'], $currentUser['username']);

        if ($result['success']) {
            $response->json([
                'success' => true,
                'message' => $result['message'],
                'bill_id' => $result['billId'],
                'total_amount' => $result['totalAmount'] ?? 0
            ], 201);
        } else {
            $response->json(['success' => false, 'message' => $result['message']], 500);
        }
    }

    public function apiGetBills(Request $request, Response $response)
    {
        // Auth check via middleware in router
        try {
            $bills = $this->billService->getAllBills();
            $response->json(['success' => true, 'data' => $bills]);
        } catch (\Exception $e) {
            error_log("API Get Bills Error: " . $e->getMessage());
            $response->json(['success' => false, 'message' => 'Error fetching bills.'], 500);
        }
    }
}
