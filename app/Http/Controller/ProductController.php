<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ProductService; // Assuming you keep the service layer

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function index()
    {
        // Products will be loaded by JS via API call in the Blade template
        return view('product.index', [
            'pageTitle' => 'Product Management',
        ]);
    }
}