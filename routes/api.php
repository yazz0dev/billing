<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\MobileScannerController;
use App\Http\Controllers\Api\SalesController;

// Public API routes (if any)
// Route::post('/some-public-endpoint', [SomeController::class, 'method']);

Route::middleware(['auth:sanctum'])->group(function () { // Use 'auth:sanctum' for API token authentication

    Route::middleware('role:admin')->group(function() {
        Route::apiResource('products', ApiProductController::class); // Manages product CRUD for admin
        Route::get('sales', [SalesController::class, 'index']); // GET /api/sales
    });

    Route::prefix('bills')->middleware('role:staff,admin')->group(function () {
        Route::post('generate', [BillController::class, 'generateBill']); // POST /api/bills/generate
        Route::get('/', [BillController::class, 'getBills']);          // GET /api/bills
    });

    Route::prefix('notifications')->group(function () {
        Route::post('fetch', [NotificationController::class, 'fetchNotifications']);       // POST /api/notifications/fetch
        Route::post('mark-seen', [NotificationController::class, 'markSeen']); // POST /api/notifications/mark-seen
    });

    Route::prefix('scanner')->middleware('role:staff,admin')->group(function() {
        Route::post('activate-pos', [MobileScannerController::class, 'activateDesktopScanning']);
        Route::post('deactivate-pos', [MobileScannerController::class, 'deactivateDesktopScanning']);
        Route::get('check-pos-activation', [MobileScannerController::class, 'checkDesktopActivation']);
        Route::post('activate-mobile', [MobileScannerController::class, 'activateMobileSession']);
        Route::post('submit-scan', [MobileScannerController::class, 'submitScannedProduct']);
        Route::get('items', [MobileScannerController::class, 'getScannedItemsForDesktop']);
    });

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
