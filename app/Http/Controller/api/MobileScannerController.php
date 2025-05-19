<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\MobileScannerService; // You will create this service
use Illuminate\Support\Facades\Auth;

class MobileScannerController extends Controller
{
    protected MobileScannerService $scannerService;

    public function __construct(MobileScannerService $scannerService)
    {
        $this->scannerService = $scannerService;
    }

    public function activateDesktopScanning(Request $request)
    {
        $user = Auth::user();
        $result = $this->scannerService->activateDesktopScanning($user->id, $user->username, $request->session()->getId());
        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function deactivateDesktopScanning(Request $request)
    {
        $user = Auth::user();
        $result = $this->scannerService->deactivateDesktopScanning($user->id);
        return response()->json($result);
    }

    public function checkDesktopActivation(Request $request)
    {
        $user = Auth::user();
        $result = $this->scannerService->checkDesktopActivation($user->id);
        return response()->json($result);
    }

    public function activateMobileSession(Request $request)
    {
        $user = Auth::user();
        $result = $this->scannerService->activateMobileSession($user->id, $request->session()->getId());
        return response()->json($result);
    }

    public function submitScannedProduct(Request $request)
    {
        $validatedData = $request->validate([
            'scanned_product_id' => 'required|string',
            'quantity' => 'sometimes|integer|min:1',
        ]);
        $user = Auth::user();
        $result = $this->scannerService->submitScannedProduct(
            $user->id,
            $request->session()->getId(),
            $validatedData['scanned_product_id'],
            $validatedData['quantity'] ?? 1
        );
        return response()->json($result, $result['success'] ? 200 : ($result['code'] ?? 500));
    }

    public function getScannedItemsForDesktop(Request $request)
    {
        $user = Auth::user();
        $result = $this->scannerService->getScannedItemsForDesktop($user->id);
        return response()->json($result);
    }
}