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
$pageTitle = "Admin Dashboard";
$bodyClass = "admin-page";

// Page-specific scripts
$pageScripts = [
    '/billing/js/admin-dashboard.js' // Create this if needed later
];

// Include header AFTER authentication check
require_once '../includes/header.php';
?>

<h1 class="page-title">Admin Dashboard</h1>

<section class="content-section glass">
    <h2 class="section-title">Manage Products</h2>
    <form id="addProductForm">
        <div class="form-group">
            <label for="productName">Product Name</label>
            <input type="text" id="productName" name="name" placeholder="Enter product name" required>
        </div>
        <div class="form-group">
            <label for="productPrice">Price (₹)</label>
            <input type="number" id="productPrice" name="price" placeholder="0.00" step="0.01" min="0" required>
        </div>
        <div class="form-group">
            <label for="productStock">Stock Quantity</label>
            <input type="number" id="productStock" name="stock" placeholder="0" min="0" required>
        </div>
        <button type="submit" class="btn w-full">Add Product</button>
    </form>
</section>

<section class="content-section glass">
    <h2 class="section-title">Sales Data</h2>
    <button id="viewSales" class="btn">View All Sales</button>
    <div id="salesData" class="mt-3">
        <!-- Sales data will be populated here -->
        <p class="text-center text-light">Click "View All Sales" to load data.</p>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addProductForm = document.getElementById('addProductForm');
    const viewSalesButton = document.getElementById('viewSales');
    const salesDataDiv = document.getElementById('salesData');

    if (addProductForm) {
        addProductForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'addProduct');
            
            try {
                const response = await fetch('/billing/server.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.status === 'success') {
                    window.popupNotification.success(result.message || "Product added successfully!", "Product Added");
                    e.target.reset(); // Clear form
                } else {
                    window.popupNotification.error(result.message || "Failed to add product. Please try again.", "Error");
                }
            } catch (error) {
                console.error("Add product error:", error);
                window.popupNotification.error("A server error occurred. Please try again later.", "Server Error");
            }
        });
    }

    if (viewSalesButton) {
        viewSalesButton.addEventListener('click', async () => {
            salesDataDiv.innerHTML = '<p class="text-center text-light">Loading sales data...</p>'; // Loading state
            try {
                const response = await fetch('/billing/server.php?action=getSales');
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const sales = await response.json();
                
                if (sales && sales.length === 0) {
                    salesDataDiv.innerText = "No sales data available at the moment.";
                    window.popupNotification.info("No sales records found.", "Sales Data");
                } else if (sales) {
                    salesDataDiv.innerText = JSON.stringify(sales, null, 2);
                    window.popupNotification.success(`Successfully loaded ${sales.length} sales records.`, "Sales Data Loaded");
                } else {
                    throw new Error("Invalid sales data received.");
                }
            } catch (error) {
                console.error("Fetch sales error:", error);
                salesDataDiv.innerText = "Failed to load sales data. Please try again.";
                window.popupNotification.error("Failed to fetch sales data. Check console for details.", "Data Error");
            }
        });
    }
});
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?>
