<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ProductService;
use App\Services\BillService;
use App\Models\Product; // For datalist

class StaffController extends Controller
{
    protected ProductService $productService;
    protected BillService $billService;

    public function __construct(ProductService $productService, BillService $billService)
    {
        $this->productService = $productService;
        $this->billService = $billService;
    }

    public function pos()
    {
        $products = Product::orderBy('name')->get(['_id', 'name', 'price', 'stock']);
        return view('staff.pos', [
            'pageTitle' => 'Supermarket Billing (POS)',
            'products' => $products,
        ]);
    }

    public function billView()
    {
        // Data will be loaded via JS in the view from API
        return view('staff.bill_view', [
            'pageTitle' => 'Bill History',
        ]);
    }

    public function showScannerPage()
    {
        return view('staff.mobile_scanner', [
            'pageTitle' => 'Mobile Barcode Scanner',
            'pageScripts' => [
                 asset('js/html5-qrcode.min.js'),
                 asset('js/mobile-scanner.js')
            ],
            'bodyClass' => 'layout-minimal',
            'layout' => 'layouts.minimal' // Specify layout for Blade
        ]);
    }
}