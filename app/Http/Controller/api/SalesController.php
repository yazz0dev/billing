<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\SalesRepository; // Or SalesService if you create one

class SalesController extends Controller
{
    protected SalesRepository $salesRepository;

    public function __construct(SalesRepository $salesRepository)
    {
        $this->salesRepository = $salesRepository;
    }

    public function index()
    {
        try {
            $sales = $this->salesRepository->getAllSales();
            return response()->json(['success' => true, 'data' => $sales]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching sales data: ' . $e->getMessage()], 500);
        }
    }
}