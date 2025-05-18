<?php // src/Product/ProductService.php

namespace App\Product;

use App\Notification\NotificationService;

class ProductService
{
    private ProductRepository $productRepository;
    private NotificationService $notificationService;

    public function __construct()
    {
        $this->productRepository = new ProductRepository();
        $this->notificationService = new NotificationService();
    }

    public function getAllProducts(): array
    {
        return $this->productRepository->findAll();
    }

    public function getProductById(string $id): ?array // Return as array for controller
    {
        $productDoc = $this->productRepository->findById($id);
        return $productDoc ? (array) $productDoc->getArrayCopy() : null;
    }

    public function addProduct(string $name, float $price, int $stock): ?string
    {
        $name = trim(htmlspecialchars($name, ENT_QUOTES | ENT_HTML5));
        if (empty($name) || $price < 0 || $stock < 0) {
            throw new \InvalidArgumentException("Invalid product data provided.");
        }
        $productId = $this->productRepository->create($name, $price, $stock);
        if ($productId) {
            $this->notificationService->create(
                "Product '{$name}' added successfully.",
                'success', 'admin', 5000, 'Product Added'
            );
        }
        return $productId;
    }

    public function updateProduct(string $id, string $name, float $price, int $stock): bool
    {
        $name = trim(htmlspecialchars($name, ENT_QUOTES | ENT_HTML5));
        if (empty($name) || $price < 0 || $stock < 0) {
            throw new \InvalidArgumentException("Invalid product data for update.");
        }
        $success = $this->productRepository->update($id, ['name' => $name, 'price' => $price, 'stock' => $stock]);
        if ($success) {
            $this->notificationService->create(
                "Product '{$name}' (ID: {$id}) updated.",
                'info', 'admin', 5000, 'Product Updated'
            );
        }
        return $success;
    }

    public function deleteProduct(string $id): bool
    {
        $product = $this->productRepository->findById($id);
        if (!$product) return false;

        $success = $this->productRepository->delete($id);
        if ($success) {
            $this->notificationService->create(
                "Product '{$product->name}' deleted.",
                'warning', 'admin', 5000, 'Product Deleted'
            );
        }
        return $success;
    }
}
