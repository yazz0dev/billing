<?php // templates/admin/dashboard.php
// $pageTitle, $totalSales, $totalProducts, $csrf_token_name, $csrf_token_value available
?>
<h1 class="page-title"><?php echo $e($pageTitle); ?></h1>

<section class="content-section glass">
    <h2 class="section-title">Overview</h2>
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Sales Orders</h3>
            <p class="stat-value"><?php echo $e($totalSales ?? 0); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Products</h3>
            <p class="stat-value"><?php echo $e($totalProducts ?? 0); ?></p>
        </div>
        <!-- Add more stats as needed -->
    </div>
</section>

<section class="content-section glass">
    <h2 class="section-title">Manage Products (Quick Add)</h2>
    <form id="addProductFormAdmin"> <!-- Different ID from product page form if JS is different -->
        <input type="hidden" name="<?php echo $e($csrf_token_name); ?>" value="<?php echo $e($csrf_token_value); ?>">
        <div class="form-group">
            <label for="adminProductName">Product Name</label>
            <input type="text" id="adminProductName" name="name" placeholder="Enter product name" required>
        </div>
        <div class="form-group">
            <label for="adminProductPrice">Price (₹)</label>
            <input type="number" id="adminProductPrice" name="price" placeholder="0.00" step="0.01" min="0" required>
        </div>
        <div class="form-group">
            <label for="adminProductStock">Stock Quantity</label>
            <input type="number" id="adminProductStock" name="stock" placeholder="0" min="0" required>
        </div>
        <button type="submit" class="btn w-full">Add Product</button>
    </form>
</section>

<section class="content-section glass">
    <h2 class="section-title">Sales Data</h2>
    <button id="viewSalesBtn" class="btn">View All Sales</button>
    <div id="salesDataDisplay" class="mt-3" style="max-height: 300px; overflow-y: auto; background: #eee; padding: 10px; border-radius: 5px;">
        <p class="text-center">Click "View All Sales" to load data.</p>
    </div>
</section>

<?php
// Example of page-specific scripts
$pageScripts = ['/js/admin-dashboard.js']; // Updated to use the external file
?>
