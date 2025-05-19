<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\ProductController as WebProductController;
use App\Http\Controllers\ProfileController; // From Breeze

Route::get('/', [HomeController::class, 'index'])->name('home');

// Auth routes are defined in routes/auth.php by Breeze
require __DIR__.'/auth.php';

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        if ($user->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->hasRole('staff')) {
            return redirect()->route('staff.pos');
        }
        // Default Breeze dashboard view if no specific role redirect
        return view('dashboard');
    })->name('dashboard'); // Generic dashboard after login

    // Admin Routes
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('products', [WebProductController::class, 'index'])->name('products.index');
        // Add other admin web routes here
    });

    // Staff Routes (also accessible by admin)
    Route::middleware('role:staff,admin')->prefix('staff')->name('staff.')->group(function () {
        Route::get('pos', [StaffController::class, 'pos'])->name('pos');
        Route::get('bills', [StaffController::class, 'billView'])->name('bills.view');
        Route::get('mobile-scanner', [StaffController::class, 'showScannerPage'])->name('scanner.mobile.page');
        // Add other staff web routes here
    });

    // Profile routes (from Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});