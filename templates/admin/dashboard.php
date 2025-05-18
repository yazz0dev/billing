
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
$pageScripts = ['/js/admin-dashboard.js'];
?>

<script>
// Contents of public/js/admin-dashboard.js would be:
document.addEventListener('DOMContentLoaded', function() {
    const addProductForm = document.getElementById('addProductFormAdmin');
    const viewSalesButton = document.getElementById('viewSalesBtn');
    const salesDataDiv = document.getElementById('salesDataDisplay');
    const csrfToken = document.querySelector('input[name="<?php echo $e($csrf_token_name); ?>"]').value;


    if (addProductForm) {
        addProductForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());
            delete data['<?php echo $e($csrf_token_name); ?>']; // CSRF is in header for API

            try {
                const response = await fetch('/api/products', { // API endpoint for adding products
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken // Send CSRF if your API setup requires it
                    },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                
                if (result.success) {
                    window.popupNotification.success(result.message || "Product added successfully!", "Product Added");
                    e.target.reset();
                } else {
                    window.popupNotification.error(result.message || "Failed to add product.", "Error");
                }
            } catch (error) {
                console.error("Add product error:", error);
                window.popupNotification.error("A server error occurred.", "Server Error");
            }
        });
    }

    if (viewSalesButton) {
        viewSalesButton.addEventListener('click', async () => {
            salesDataDiv.innerHTML = '<p class="text-center">Loading sales data...</p>';
            try {
                const response = await fetch('/api/sales'); // API endpoint for sales
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                
                if (result.success && result.data && result.data.length === 0) {
                    salesDataDiv.textContent = "No sales data available.";
                } else if (result.success && result.data) {
                    // Render sales data more nicely than just JSON.stringify
                    let html = '<ul>';
                    result.data.forEach(sale => {
                        html += `<li>Bill ID: ${sale._id.$oid} - Amount: ${sale.total_amount} - Date: ${new Date(sale.created_at.$date).toLocaleDateString()}</li>`;
                    });
                    html += '</ul>';
                    salesDataDiv.innerHTML = html;
                    window.popupNotification.success(`Loaded ${result.data.length} sales records.`, "Sales Data Loaded");
                } else {
                    throw new Error(result.message || "Invalid sales data received.");
                }
            } catch (error) {
                console.error("Fetch sales error:", error);
                salesDataDiv.textContent = "Failed to load sales data.";
                window.popupNotification.error("Failed to fetch sales data.", "Data Error");
            }
        });
    }
});
</script>
