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

    public function index(Request $request, Response $response)
    {
        $products = $this->productService->getAllProducts();
        $this->render('product/index.php', [
            'pageTitle' => 'Product Management',
            'products' => $products,
            'csrf_token_name' => 'product_action_csrf',
            'csrf_token_value' => $this->generateCsrfToken('product_actions')
        ]);
    }

    public function apiGetProducts(Request $request, Response $response)
    {
        try {
            $products = $this->productService->getAllProducts();
            $productsArray = array_map(fn($doc) => (array) $doc->getArrayCopy(), $products);
            $response->json(['success' => true, 'data' => $productsArray]);
        } catch (\Exception $e) {
            $response->json(['success' => false, 'message' => 'Error fetching products: ' . $e->getMessage()], 500);
        }
    }

    public function apiAddProduct(Request $request, Response $response)
    {
        $name = $request->json('name', $request->post('name'));
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
    
    // MODIFIED SIGNATURE
    public function apiGetProductById(Request $request, Response $response, string $id)
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

    // MODIFIED SIGNATURE
    public function apiUpdateProduct(Request $request, Response $response, string $id)
    {
        $name = $request->json('name', $request->post('name'));
        $price = (float) $request->json('price', $request->post('price'));
        $stock = (int) $request->json('stock', $request->post('stock'));

        try {
            $success = $this->productService->updateProduct($id, $name, $price, $stock);
            if ($success) {
                $response->json(['success' => true, 'message' => 'Product updated successfully.']);
            } else {
                $response->json(['success' => false, 'message' => 'Failed to update product or product not found.'], 400);
            }
        } catch (\InvalidArgumentException $e) {
            $response->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("API Update Product Error (ID: {$id}): " . $e->getMessage());
            $response->json(['success' => false, 'message' => 'Server error updating product.'], 500);
        }
    }

    // MODIFIED SIGNATURE
    public function apiDeleteProduct(Request $request, Response $response, string $id)
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