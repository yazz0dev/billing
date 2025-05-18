<?php // src/Admin/AdminController.php

namespace App\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Product\ProductService; // For product count example
use App\Auth\UserRepository;    // For user count example

class AdminController extends Controller
{
    private SalesRepository $salesRepository;
    private ProductService $productService; // Example for dashboard stats
    private UserRepository $userRepository; // Example

    public function __construct()
    {
        parent::__construct();
        $this->salesRepository = new SalesRepository();
        $this->productService = new ProductService();
        $this->userRepository = new UserRepository();
    }

    public function dashboard(Request $request, Response $response)
    {
        // Fetch data for the dashboard
        // For simplicity, we'll just pass a message. In a real app, fetch stats.
        $totalSales = count($this->salesRepository->getAllSales()); // Example
        $totalProducts = count($this->productService->getAllProducts()); // Example
        // $totalUsers = $this->userRepository->countAllUsers(); // Add method to UserRepository

        $this->render('admin/dashboard.php', [
            'pageTitle' => 'Admin Dashboard',
            'totalSales' => $totalSales,
            'totalProducts' => $totalProducts,
            // 'totalUsers' => $totalUsers,
            'csrf_token_name' => 'admin_action_csrf',
            'csrf_token_value' => $this->generateCsrfToken('admin_actions')
        ]);
    }

    // --- API Methods ---
    public function apiGetSales(Request $request, Response $response)
    {
        try {
            $salesDocs = $this->salesRepository->getAllSales();
            $salesArray = array_map(fn($doc) => (array) $doc->getArrayCopy(), $salesDocs);
            $response->json(['success' => true, 'data' => $salesArray]);
        } catch (\Exception $e) {
            error_log("API Get Sales Error: " . $e->getMessage());
            $response->json(['success' => false, 'message' => 'Error fetching sales data.'], 500);
        }
    }
}
