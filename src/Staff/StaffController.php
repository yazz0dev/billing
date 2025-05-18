<?php // src/Staff/StaffController.php

namespace App\Staff;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Product\ProductService; // For product list on POS
use App\Billing\BillService;    // For bill history

class StaffController extends Controller
{
    private ProductService $productService;
    private BillService $billService;

    public function __construct()
    {
        parent::__construct();
        $this->productService = new ProductService();
        $this->billService = new BillService();
    }

    public function pos(Request $request, Response $response)
    {
        $products = $this->productService->getAllProducts();
        $productsArray = array_map(fn($doc) => (array) $doc->getArrayCopy(), $products);

        $this->render('staff/pos.php', [
            'pageTitle' => 'Supermarket Billing (POS)',
            'products' => $productsArray,
            'csrf_token_name' => 'pos_action_csrf',
            'csrf_token_value' => $this->generateCsrfToken('pos_actions')
        ]);
    }

    public function billView(Request $request, Response $response)
    {
        $bills = $this->billService->getAllBills(); // Fetches all bills for display
        $products = $this->productService->getAllProducts(); // For product name lookup
         $productsLookup = [];
        foreach ($products as $productDoc) {
            $product = (array) $productDoc->getArrayCopy();
            $productsLookup[(string)$product['_id']] = $product['name'];
        }


        $this->render('staff/bill_view.php', [
            'pageTitle' => 'Bill History',
            'bills' => $bills,
            'productsLookup' => $productsLookup
        ]);
    }
}
