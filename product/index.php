<?php
// Check authentication FIRST - before any output
session_start();

// Check if user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Redirect non-admin users
    header('Location: /billing/login.php?error=unauthorized');
    exit;
}

// Set page variables - AFTER authentication check
$pageTitle = "Product Management";
$bodyClass = "";

// Include header
require_once '../includes/header.php';
?>

<h1 class="page-title">Product Management</h1>

<!-- Add Product Form -->
<section class="content-section glass">
    <h2 class="section-title">Add New Product</h2>
    <form id="addProductForm">
        <div class="flex flex-col md:flex-row gap-2">
            <div class="form-group flex-grow">
                <label for="productName">Product Name</label>
                <input type="text" id="productName" name="name" placeholder="Enter product name" required>
            </div>
            
            <div class="form-group">
                <label for="productPrice">Price</label>
                <input type="number" id="productPrice" name="price" placeholder="0.00" min="0.01" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="productStock">Stock</label>
                <input type="number" id="productStock" name="stock" placeholder="0" min="1" step="1" required>
            </div>
        </div>
        
        <button type="submit" class="btn">Add Product</button>
    </form>
</section>

<!-- Product List Section -->
<section class="content-section glass mt-4">
    <h2 class="section-title">Product List</h2>
    <div id="productList" class="product-grid">
        <!-- Products will be loaded here -->
        <p class="text-center text-light">Loading products...</p>
    </div>
</section>

<script>
// Initialize page on load
document.addEventListener('DOMContentLoaded', async function() {
    const productListDiv = document.getElementById('productList');
    const addProductForm = document.getElementById('addProductForm');
    
    // Load products
    try {
        await loadProducts();
    } catch (error) {
        console.error("Failed to load products:", error);
    }
    
    // Add product form submission
    addProductForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const productData = {
            name: formData.get('name'),
            price: parseFloat(formData.get('price')),
            stock: parseInt(formData.get('stock'))
        };
        
        try {
            const response = await fetch('/billing/server.php?action=addProduct', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(productData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                window.popupNotification.success("Product added successfully!");
                addProductForm.reset();
                await loadProducts(); // Reload the product list
            } else {
                window.popupNotification.error("Failed to add product: " + result.message);
            }
        } catch (error) {
            console.error("Error adding product:", error);
            window.popupNotification.error("An error occurred. Please try again.");
        }
    });
    
    async function loadProducts() {
        try {
            const response = await fetch('/billing/server.php?action=getProducts');
            const products = await response.json();
            
            if (products && products.length > 0) {
                productListDiv.innerHTML = products.map(product => `
                    <div class="product-card card-base">
                        <h3>${product.name}</h3>
                        <p class="card-meta">Stock: ${product.stock} units</p>
                        <p class="mb-3">Price: $${parseFloat(product.price).toFixed(2)}</p>
                        <div class="card-actions">
                            <button class="btn" onclick="editProduct(${product.id})">Edit</button>
                            <button class="btn" style="background: linear-gradient(135deg, #ef4444, #f43f5e);" 
                                onclick="deleteProduct(${product.id})">Delete</button>
                        </div>
                    </div>
                `).join('');
            } else {
                productListDiv.innerHTML = '<p class="text-center text-light glass p-3">No products found.</p>';
            }
        } catch (error) {
            console.error("Failed to fetch products:", error);
            productListDiv.innerHTML = '<p class="text-center text-light glass p-3">Could not load products. Please try again later.</p>';
            window.popupNotification.error("Failed to load products.", "Data Error");
        }
    }
    
    // Make functions available globally
    window.loadProducts = loadProducts;
    
    window.editProduct = async function(productId) {
        try {
            const response = await fetch(`/billing/server.php?action=getProduct&id=${productId}`);
            const product = await response.json();
            
            if (product) {
                // For simplicity, prompt for edits
                const name = prompt("Product Name:", product.name);
                const price = prompt("Price:", product.price);
                const stock = prompt("Stock:", product.stock);
                
                if (name && price && stock) {
                    const updatedProduct = {
                        id: productId,
                        name: name,
                        price: parseFloat(price),
                        stock: parseInt(stock)
                    };
                    
                    const updateResponse = await fetch('/billing/server.php?action=updateProduct', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(updatedProduct)
                    });
                    
                    const result = await updateResponse.json();
                    
                    if (result.success) {
                        window.popupNotification.success("Product updated successfully!");
                        await loadProducts(); // Reload the product list
                    } else {
                        window.popupNotification.error("Failed to update product: " + result.message);
                    }
                }
            } else {
                window.popupNotification.error("Product not found.");
            }
        } catch (error) {
            console.error("Error editing product:", error);
            window.popupNotification.error("An error occurred. Please try again.");
        }
    };
    
    window.deleteProduct = function(productId) {
        if (confirm("Are you sure you want to delete this product?")) {
            fetch(`/billing/server.php?action=deleteProduct&id=${productId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    window.popupNotification.success("Product deleted successfully!");
                    loadProducts(); // Reload the product list
                } else {
                    window.popupNotification.error("Failed to delete product: " + result.message);
                }
            })
            .catch(error => {
                console.error("Error deleting product:", error);
                window.popupNotification.error("An error occurred. Please try again.");
            });
        }
    };
});
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?>
