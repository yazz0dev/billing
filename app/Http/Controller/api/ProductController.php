<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ProductService;
use App\Models\Product; // Use Eloquent model directly for simple API
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function index()
    {
        $products = Product::orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $products]);
    }

    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:products,name',
                'price' => 'required|numeric|min:0',
                'stock' => 'required|integer|min:0',
                'low_stock_threshold' => 'sometimes|integer|min:0',
                'barcode' => 'sometimes|nullable|string|unique:products,barcode',
            ]);

            $product = $this->productService->addProduct(
                $validatedData['name'],
                (float)$validatedData['price'],
                (int)$validatedData['stock'],
                isset($validatedData['low_stock_threshold']) ? (int)$validatedData['low_stock_threshold'] : 5,
                $validatedData['barcode'] ?? null
            );

            return response()->json(['success' => true, 'message' => 'Product added successfully.', 'data' => $product], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }
        return response()->json(['success' => true, 'data' => $product]);
    }

    public function update(Request $request, string $id)
    {
        try {
            $product = Product::findOrFail($id);
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:products,name,' . $id . ',_id',
                'price' => 'required|numeric|min:0',
                'stock' => 'required|integer|min:0',
                'low_stock_threshold' => 'sometimes|integer|min:0',
                'barcode' => 'sometimes|nullable|string|unique:products,barcode,' . $id . ',_id',
            ]);
            
            $updated = $this->productService->updateProduct(
                $id,
                $validatedData['name'],
                (float)$validatedData['price'],
                (int)$validatedData['stock'],
                isset($validatedData['low_stock_threshold']) ? (int)$validatedData['low_stock_threshold'] : $product->low_stock_threshold,
                $validatedData['barcode'] ?? $product->barcode
            );

            if ($updated) {
                return response()->json(['success' => true, 'message' => 'Product updated successfully.', 'data' => Product::find($id)]);
            }
            return response()->json(['success' => false, 'message' => 'Product update failed.'], 500); // Should not happen if validation passes

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $product = Product::findOrFail($id);
            $deleted = $this->productService->deleteProduct($id);
            if ($deleted) {
                return response()->json(['success' => true, 'message' => 'Product deleted successfully.']);
            }
            return response()->json(['success' => false, 'message' => 'Product deletion failed.'], 500);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}