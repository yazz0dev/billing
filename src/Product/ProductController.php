<?php // src/Product/ProductController.php

namespace App\Product;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class ProductController extends Controller
{
    private ProductService $productService;

    public function __construct()
    {
        parent::__construct();
        $this->productService = new ProductService();
    }

    // For HTML Page (Admin Product Management)
    public function index(Request $request, Response $response)
    {
        // This method is for rendering the HTML page for product management
        $products = $this->productService->getAllProducts();
        $this->render('product/index.php', [
            'pageTitle' => 'Product Management',
            'products' => $products,
            'csrf_token_name' => 'product_action_csrf', // Example CSRF for forms on this page
            'csrf_token_value' => $this->generateCsrfToken('product_actions')
        ]);
    }

    // --- API Methods ---

    public function apiGetProducts(Request $request, Response $response)
    {
        try {
            $products = $this->productService->getAllProducts();
            // Convert BSON documents to arrays for JSON response
            $productsArray = array_map(function ($doc) {
                return (array) $doc->getArrayCopy(); // Or a more specific DTO/Transformer
            }, $products);
            $response->json(['success' => true, 'data' => $productsArray]);
        } catch (\Exception $e) {
            $response->json(['success' => false, 'message' => 'Error fetching products: ' . $e->getMessage()], 500);
        }
    }

    public function apiAddProduct(Request $request, Response $response)
    {
        // Assuming CSRF check is handled by a global middleware for API POST/PUT/DELETE if needed,
        // or verified here if specific to this action. For simplicity, skipping API CSRF here.
        
        $name = $request->json('name', $request->post('name')); // Handle JSON or form data
        $price = (float) $request->json('price', $request->post('price'));
        $stock = (int) $request->json('stock', $request->post('stock'));

        try {
            $productId = $this->productService->addProduct($name, $price, $stock);
            if ($productId) {
                $response->json(['success' => true, 'message' => 'Product added successfully.', 'id' => $productId], 201);
            } else {
                $response->json(['success' => false, 'message' => 'Failed to add product.'], 400);
            }
        } catch (\InvalidArgumentException $e) {
            $response->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("API Add Product Error: " . $e->getMessage());
            $response->json(['success' => false, 'message' => 'Server error adding product.'], 500);
        }
    }
    
    public function apiGetProductById(Request $request, Response $response, string $id) // $id comes from route param
    {
        try {
            $product = $this->productService->getProductById($id);
            if ($product) {
                $response->json(['success' => true, 'data' => $product]);
            } else {
                $response->json(['success' => false, 'message' => 'Product not found.'], 404);
            }
        } catch (\Exception $e) {
            $response->json(['success' => false, 'message' => 'Error fetching product: ' . $e->getMessage()], 500);
        }
    }


    public function apiUpdateProduct(Request $request, Response $response, string $id) // $id from route param
    {
        $name = $request->json('name', $request->post('name'));
        $price = (float) $request->json('price', $request->post('price'));
        $stock = (int) $request->json('stock', $request->post('stock'));

        try {
            $success = $this->productService->updateProduct($id, $name, $price, $stock);
            if ($success) {
                $response->json(['success' => true, 'message' => 'Product updated successfully.']);
            } else {
                // Could be not found or data validation issue handled by service
                $response->json(['success' => false, 'message' => 'Failed to update product or product not found.'], 400);
            }
        } catch (\InvalidArgumentException $e) {
            $response->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("API Update Product Error (ID: {$id}): " . $e->getMessage());
            $response->json(['success' => false, 'message' => 'Server error updating product.'], 500);
        }
    }

    public function apiDeleteProduct(Request $request, Response $response, string $id) // $id from route param
    {
        try {
            $success = $this->productService->deleteProduct($id);
            if ($success) {
                $response->json(['success' => true, 'message' => 'Product deleted successfully.']);
            } else {
                $response->json(['success' => false, 'message' => 'Product not found or could not be deleted.'], 404);
            }
        } catch (\Exception $e) {
            error_log("API Delete Product Error (ID: {$id}): " . $e->getMessage());
            $response->json(['success' => false, 'message' => 'Server error deleting product.'], 500);
        }
    }
}
