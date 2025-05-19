<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\ProductController as WebProductController; // Renamed to avoid conflict
// Auth routes are typically handled by Breeze, e.g., Auth\AuthenticatedSessionController

Route::get('/', [HomeController::class, 'index'])->name('home');

// Breeze typically adds auth routes: login, register, forgot-password, etc.
// If you need to customize them, you can publish Breeze views/routes.

Route::middleware(['auth', 'verified'])->group(function () { // 'verified' if using email verification

    Route::get('/dashboard', function () { // Example generic dashboard
        if (auth()->user()->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }
        if (auth()->user()->hasRole('staff')) {
            return redirect()->route('staff.pos');
        }
        return view('dashboard'); // A default dashboard
    })->name('dashboard');


    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('products', [WebProductController::class, 'index'])->name('products.index');
        // Add other admin web routes
    });

    Route::middleware('role:staff,admin')->prefix('staff')->name('staff.')->group(function () {
        Route::get('pos', [StaffController::class, 'pos'])->name('pos');
        Route::get('bills', [StaffController::class, 'billView'])->name('bills.view');
        Route::get('mobile-scanner', [StaffController::class, 'showScannerPage'])->name('scanner.mobile.page');
        // Add other staff web routes
    });

    // Profile routes from Breeze usually here
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php'; // Breeze auth routes
