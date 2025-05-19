<?php

namespace App\Services;

use App\Models\Product;
use App\Services\NotificationService; // Laravel will auto-inject via constructor
use Illuminate\Support\Str;

class ProductService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function addProduct(string $name, float $price, int $stock, int $lowStockThreshold = 5, ?string $barcode = null): Product
    {
        $name = trim(strip_tags($name));
        if (empty($name) || $price < 0 || $stock < 0) {
            throw new \InvalidArgumentException("Invalid product data provided.");
        }

        $product = Product::create([
            'name' => $name,
            'price' => $price,
            'stock' => $stock,
            'low_stock_threshold' => $lowStockThreshold,
            'barcode' => $barcode,
        ]);

        $this->notificationService->create(
            "Product '{$name}' added successfully.",
            'success', 'admin', 5000, 'Product Added'
        );
        return $product;
    }

    public function updateProduct(string $id, string $name, float $price, int $stock, int $lowStockThreshold, ?string $barcode = null): bool
    {
        $product = Product::findOrFail($id);
        $name = trim(strip_tags($name));
        if (empty($name) || $price < 0 || $stock < 0) {
            throw new \InvalidArgumentException("Invalid product data for update.");
        }

        $updated = $product->update([
            'name' => $name,
            'price' => $price,
            'stock' => $stock,
            'low_stock_threshold' => $lowStockThreshold,
            'barcode' => $barcode,
        ]);

        if ($updated) {
            $this->notificationService->create(
                "Product '{$name}' (ID: {$id}) updated.",
                'info', 'admin', 5000, 'Product Updated'
            );
        }
        return $updated;
    }

    public function deleteProduct(string $id): bool
    {
        $product = Product::findOrFail($id);
        $productName = $product->name; // Get name before deleting
        $deleted = $product->delete();

        if ($deleted) {
            $this->notificationService->create(
                "Product '{$productName}' deleted.",
                'warning', 'admin', 5000, 'Product Deleted'
            );
        }
        return $deleted;
    }

    public function updateStock(string $productId, int $newStock): bool
    {
        $product = Product::findOrFail($productId);
        return $product->update(['stock' => $newStock]);
    }

    public function getProductById(string $id): ?Product
    {
        return Product::find($id);
    }

    public function getAllProducts()
    {
        return Product::orderBy('name')->get();
    }
}