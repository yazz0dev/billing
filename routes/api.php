<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\Api\BillController as ApiBillController;
use App\Http\Controllers\Api\NotificationController as ApiNotificationController;
use App\Http\Controllers\Api\MobileScannerController as ApiMobileScannerController;
use App\Http\Controllers\Api\SalesController as ApiSalesController;

Route::middleware(['auth:sanctum'])->group(function () {
    // User route (standard for Sanctum)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Admin specific API routes
    Route::middleware('role:admin')->group(function() {
        Route::apiResource('products', ApiProductController::class);
        Route::get('sales', [ApiSalesController::class, 'index']);
    });

    // Staff & Admin API routes
    Route::middleware('role:staff,admin')->group(function () {
        Route::post('bills/generate', [ApiBillController::class, 'generateBill']);
        Route::get('bills', [ApiBillController::class, 'getBills']);
        Route::get('bills/{id}', [ApiBillController::class, 'show']); // Added show route

        Route::prefix('scanner')->group(function() {
            Route::post('activate-pos', [ApiMobileScannerController::class, 'activateDesktopScanning']);
            Route::post('deactivate-pos', [ApiMobileScannerController::class, 'deactivateDesktopScanning']);
            Route::get('check-pos-activation', [ApiMobileScannerController::class, 'checkDesktopActivation']);
            Route::post('activate-mobile', [ApiMobileScannerController::class, 'activateMobileSession']);
            Route::post('submit-scan', [ApiMobileScannerController::class, 'submitScannedProduct']);
            Route::get('items', [ApiMobileScannerController::class, 'getScannedItemsForDesktop']);
        });
    });
    
    // Authenticated user API routes (any role)
    Route::prefix('notifications')->group(function () {
        Route::post('fetch', [ApiNotificationController::class, 'fetchNotifications']);
        Route::post('mark-seen', [ApiNotificationController::class, 'markSeen']);
    });
});