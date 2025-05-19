<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\BillService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BillController extends Controller
{
    protected BillService $billService;

    public function __construct(BillService $billService)
    {
        $this->billService = $billService;
    }

    public function generateBill(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|string|exists:products,_id', // Ensure product_id exists in products collection
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0', // Price at time of adding to cart
            ]);

            $user = Auth::user();
            $result = $this->billService->generateBill($validatedData['items'], $user->id, $user->username);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'bill_id' => $result['billId'],
                    'total_amount' => $result['totalAmount']
                ], 201);
            } else {
                return response()->json(['success' => false, 'message' => $result['message']], 400); // 400 for client error (e.g. insufficient stock)
            }
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            // Log error
            return response()->json(['success' => false, 'message' => 'Server error generating bill: ' . $e->getMessage()], 500);
        }
    }

    public function getBills()
    {
        $bills = $this->billService->getAllBills();
        return response()->json(['success' => true, 'data' => $bills]);
    }
    
    // Get Bill by ID (added from old structure)
    public function show(string $id)
    {
        $bill = $this->billService->getBillById($id);
        if ($bill) {
            return response()->json(['success' => true, 'data' => $bill]);
        }
        return response()->json(['success' => false, 'message' => 'Bill not found.'], 404);
    }
}