<?php
// Check authentication FIRST - before any output
session_start();

// Check if user is staff or admin
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'staff' && $_SESSION['user_role'] !== 'admin')) {
    // Redirect unauthorized users
    header('Location: /billing/login.php?error=unauthorized');
    exit;
}

// Set page variables - AFTER authentication check
$pageTitle = "Supermarket Billing";
$bodyClass = "staff-page";

// Include header
require_once '../includes/header.php';
?>

<h1 class="page-title">Supermarket Billing</h1>

<section class="content-section glass">
    <h2 class="section-title">Add Products to Cart</h2>
    <form id="addToCartForm" autocomplete="off">
        <div class="flex flex-col gap-2 md:flex-row"> <!-- Responsive flex direction -->
            <div class="form-group flex-grow mb-0"> <!-- mb-0 to align with button if in row -->
                <label for="productSearch" class="sr-only">Search Product</label> <!-- Screen reader only label -->
                <input type="text" id="productSearch" placeholder="Search product by name..." required list="productListDatalist" class="w-full">
                <datalist id="productListDatalist"></datalist>
            </div>
            <div class="form-group w-full md:w-auto mb-0"> <!-- Control width on mobile/desktop -->
                <label for="quantityInput" class="sr-only">Quantity</label>
                <input type="number" id="quantityInput" placeholder="Qty" min="1" value="1" required class="w-full">
            </div>
            <button type="submit" class="btn w-full md:w-auto">Add to Cart</button>
        </div>
    </form>

    <div class="mt-4">
        <h3>Cart Items</h3>
        <div class="table-wrapper">
            <table id="cartTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Cart items will be added here -->
                    <tr id="emptyCartRow">
                        <td colspan="5" class="text-center">Cart is empty</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right"><strong>Grand Total:</strong></td>
                        <td id="grandTotal">₹0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="flex justify-end mt-4">
            <button id="generateBillBtn" class="btn" disabled>Generate Bill</button>
        </div>
    </div>
</section>

<script>
    // Initialize variables
    let products = [];
    let cart = [];

    // DOM elements
    const productSearch = document.getElementById('productSearch');
    const productListDatalist = document.getElementById('productListDatalist');
    const quantityInput = document.getElementById('quantityInput');
    const addToCartForm = document.getElementById('addToCartForm');
    const cartTable = document.getElementById('cartTable');
    const emptyCartRow = document.getElementById('emptyCartRow');
    const grandTotalElement = document.getElementById('grandTotal');
    const generateBillBtn = document.getElementById('generateBillBtn');

    // Fetch products on page load
    document.addEventListener('DOMContentLoaded', async () => {
        try {
            const response = await fetch('/billing/server.php?action=getProducts');
            const rawProducts = await response.json();
            
            // Process products to ensure proper ID format
            products = rawProducts.map(product => {
                return {
                    id: product._id.$oid || product.id, // Get ID from MongoDB format or fallback
                    name: product.name,
                    price: parseFloat(product.price),
                    stock: product.stock
                };
            });
            
            // Populate datalist for product search
            productListDatalist.innerHTML = products.map(product => 
                `<option value="${product.name}" data-id="${product.id}" data-price="${product.price}">`
            ).join('');
            
            // Initialize cart
            updateCartDisplay();
        } catch (error) {
            console.error("Failed to fetch products:", error);
            window.popupNotification.error("Failed to load products.", "Data Error");
        }
    });

    // Add to cart
    addToCartForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const productName = productSearch.value;
        const quantity = parseInt(quantityInput.value);
        
        if (!productName || quantity < 1) {
            window.popupNotification.warning("Please select a product and enter a valid quantity.");
            return;
        }
        
        const product = products.find(p => p.name === productName);
        if (!product) {
            window.popupNotification.warning("Product not found. Please select from the list.");
            return;
        }
        
        if (quantity > product.stock) {
            window.popupNotification.warning(`Only ${product.stock} units available in stock.`);
            return;
        }
        
        // Check if product already in cart
        const existingItem = cart.find(item => item.product_id === product.id);
        if (existingItem) {
            // Update quantity if already in cart
            existingItem.quantity += quantity;
            existingItem.total = existingItem.quantity * existingItem.price;
        } else {
            // Add new item to cart
            cart.push({
                product_id: product.id,
                product_name: product.name,
                price: parseFloat(product.price),
                quantity: quantity,
                total: quantity * parseFloat(product.price)
            });
        }
        
        // Update cart display
        updateCartDisplay();
        
        // Reset form
        productSearch.value = '';
        quantityInput.value = '1';
        
        window.popupNotification.success(`${product.name} added to cart.`);
    });
    
    // Update cart display
    function updateCartDisplay() {
        const tbody = cartTable.querySelector('tbody');
        
        // Show empty cart message if cart is empty
        if (cart.length === 0) {
            emptyCartRow.style.display = 'table-row';
            generateBillBtn.disabled = true;
            grandTotalElement.textContent = '₹0.00';
            return;
        }
        
        // Hide empty cart message and enable generate bill button
        emptyCartRow.style.display = 'none';
        generateBillBtn.disabled = false;
        
        // Clear tbody except for the empty cart row
        Array.from(tbody.children).forEach(child => {
            if (child !== emptyCartRow) {
                tbody.removeChild(child);
            }
        });
        
        // Add cart items
        let grandTotal = 0;
        cart.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.product_name}</td>
                <td>₹${item.price.toFixed(2)}</td>
                <td>${item.quantity}</td>
                <td>₹${item.total.toFixed(2)}</td>
                <td>
                    <button class="btn" style="padding: 0.3rem 0.6rem; background: linear-gradient(135deg, #ef4444, #f43f5e);" 
                        onclick="removeFromCart(${index})">Remove</button>
                </td>
            `;
            tbody.appendChild(tr);
            grandTotal += item.total;
        });
        
        // Update grand total
        grandTotalElement.textContent = `₹${grandTotal.toFixed(2)}`;
    }
    
    // Remove item from cart
    window.removeFromCart = function(index) {
        const item = cart[index];
        cart.splice(index, 1);
        updateCartDisplay();
        window.popupNotification.info(`${item.product_name} removed from cart.`);
    };
    
    // Generate bill
    generateBillBtn.addEventListener('click', async () => {
        if (cart.length === 0) {
            window.popupNotification.warning("Cart is empty. Add products to generate a bill.");
            return;
        }
        
        try {
            const response = await fetch('/billing/server.php?action=generateBill', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ items: cart })
            });
            
            const result = await response.json();
            
            if (result.success) {
                window.popupNotification.success("Bill generated successfully!", "Success");
                
                // Show bill details (you could print or show in a modal instead)
                const totalAmount = cart.reduce((sum, item) => sum + item.total, 0).toFixed(2);
                window.confirmNotification(
                    `<h3>Bill #${result.bill_id} Generated</h3>
                    <p>Date: ${new Date().toLocaleString()}</p>
                    <p>Total Items: ${cart.length}</p>
                    <p>Total Amount: ₹${totalAmount}</p>
                    <p>Thank you for your purchase!</p>`,
                    function() {
                        // Clear cart after bill is generated
                        cart = [];
                        updateCartDisplay();
                    }
                );
            } else {
                window.popupNotification.error("Failed to generate bill: " + result.message, "Error");
            }
        } catch (error) {
            console.error("Error generating bill:", error);
            window.popupNotification.error("An error occurred. Please try again.", "Error");
        }
    });
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?>
