<?php // templates/product/index.php
// $pageTitle, $products, $csrf_token_name, $csrf_token_value are available
?>
<h1 class="page-title"><?php echo $e($pageTitle); ?></h1>

<!-- Add Product Form -->
<section class="content-section glass">
    <h2 class="section-title">Add New Product</h2>
    <form id="addProductForm">
        <input type="hidden" name="<?php echo $e($csrf_token_name); ?>" value="<?php echo $e($csrf_token_value); ?>">
        <div class="flex flex-col md:flex-row gap-2">
            <div class="form-group flex-grow">
                <label for="productName">Product Name</label>
                <input type="text" id="productName" name="name" placeholder="Enter product name" required>
            </div>
            <div class="form-group">
                <label for="productPrice">Price (₹)</label>
                <input type="number" id="productPrice" name="price" placeholder="0.00" min="0.01" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="productStock">Stock</label>
                <input type="number" id="productStock" name="stock" placeholder="0" min="0" step="1" required>
            </div>
        </div>
        <button type="submit" class="btn">Add Product</button>
    </form>
</section>

<!-- Product List Section -->
<section class="content-section glass mt-4">
    <h2 class="section-title">Product List</h2>
    <div id="productList" class="product-grid">
        <!-- Products will be loaded here by JS -->
        <p class="text-center text-light">Loading products...</p>
    </div>
</section>

<?php $pageScripts = ['/js/admin-product-management.js']; // Example page-specific JS ?>

<script>
// This is now admin-product-management.js
// All API calls will go to /api/products (GET, POST, PUT, DELETE)
// Remember to handle CSRF for AJAX if needed, or rely on SameSite cookies + Origin check for API.
// For form submissions that reload the page (if any), CSRF is handled by hidden input.
// For SPA-like behavior with JS, send CSRF token in headers for POST/PUT/DELETE.
// Example:
// const csrfToken = document.querySelector('input[name="<?php echo $e($csrf_token_name); ?>"]').value;
// fetch('/api/products', { method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: ... })
</script>
