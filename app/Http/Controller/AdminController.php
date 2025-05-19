<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bill; // Assuming Bill model for sales count
use App\Models\Product;
use App\Services\ProductService; // If you port your service

class AdminController extends Controller
{
    // If you keep services:
    // protected ProductService $productService;
    // public function __construct(ProductService $productService)
    // {
    //     $this->productService = $productService;
    // }

    public function dashboard()
    {
        $totalSales = Bill::count(); // Example: counting all bills as sales orders
        $totalProducts = Product::count();

        // CSRF token is handled automatically by Blade's @csrf

        return view('admin.dashboard', [
            'pageTitle' => 'Admin Dashboard',
            'totalSales' => $totalSales,
            'totalProducts' => $totalProducts,
        ]);
    }
}
