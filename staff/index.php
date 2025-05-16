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

// Page-specific scripts
$pageScripts = [
    '/billing/js/qrcode.min.js' // For generating pairing QR
];

// Include header
require_once '../includes/header.php';
?>

<h1 class="page-title">Supermarket Billing</h1>

<!-- Mobile Scanner Pairing Section -->
<section class="content-section glass">
    <h2 class="section-title">Pair Mobile Scanner</h2>
    <div id="pairingUi" class="flex flex-col md:flex-row gap-2 items-start">
        <div class="flex-grow">
            <button id="setupScannerBtn" class="btn">Setup Mobile Scanner</button>
            <div id="pairingInfo" class="mt-2" style="display: none;">
                <p>Pairing ID: <strong id="pairingIdDisplay" class="text-lg"></strong></p>
                <p class="text-sm text-secondary">Ask the mobile user to enter this ID or scan the QR code on their device at <code class="text-xs"><?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>/billing/index.html</code>.</p>
                <p class="text-sm text-secondary">Pairing ID is valid for 15 minutes.</p>
            </div>
        </div>
        <div id="pairingQrCode" class="mt-2 md:mt-0" style="min-width: 128px; min-height: 128px;">
            <!-- QR Code will be rendered here -->
        </div>
    </div>
    <div id="scannerStatus" class="mt-2 text-sm"></div>
</section>

<section class="content-section glass mt-4">
    <h2 class="section-title">Add Products to Cart</h2>
    <form id="addToCartForm" autocomplete="off">
        <div class="flex flex-col gap-2 md:flex-row"> <!-- Responsive flex direction -->
            <div class="form-group flex-grow mb-0"> <!-- mb-0 to align with button if in row -->
                <label for="productSearch" class="sr-only">Search Product</label> <!-- Screen reader only label -->
                <input type="text" id="productSearch" placeholder="Search product by name or scan..." required list="productListDatalist" class="w-full">
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
    let currentPairingId = null;
    let pairingPollInterval = null;

    // DOM elements
    const productSearch = document.getElementById('productSearch');
    const productListDatalist = document.getElementById('productListDatalist');
    const quantityInput = document.getElementById('quantityInput');
    const addToCartForm = document.getElementById('addToCartForm');
    const cartTable = document.getElementById('cartTable');
    const emptyCartRow = document.getElementById('emptyCartRow');
    const grandTotalElement = document.getElementById('grandTotal');
    const generateBillBtn = document.getElementById('generateBillBtn');

    // Pairing UI elements
    const setupScannerBtn = document.getElementById('setupScannerBtn');
    const pairingInfoDiv = document.getElementById('pairingInfo');
    const pairingIdDisplay = document.getElementById('pairingIdDisplay');
    const pairingQrCodeDiv = document.getElementById('pairingQrCode');
    const scannerStatusDiv = document.getElementById('scannerStatus');
    let qrCodeInstance = null;


    // Fetch products on page load
    document.addEventListener('DOMContentLoaded', async () => {
        try {
            const response = await fetch('/billing/server.php?action=getProducts');
            const rawProducts = await response.json();
            
            products = rawProducts.map(product => {
                return {
                    id: product._id.$oid || product.id,
                    name: product.name,
                    price: parseFloat(product.price),
                    stock: parseInt(product.stock) // Ensure stock is integer
                };
            });
            
            productListDatalist.innerHTML = products.map(product => 
                `<option value="${product.name}" data-id="${product.id}" data-price="${product.price}" data-stock="${product.stock}">`
            ).join('');
            
            updateCartDisplay();
        } catch (error) {
            console.error("Failed to fetch products:", error);
            window.popupNotification.error("Failed to load products.", "Data Error");
        }
    });

    // Add to cart (manual or from scan)
    function addProductToCartById(productId, quantity = 1) {
        const product = products.find(p => p.id === productId);
        if (!product) {
            window.popupNotification.warning("Scanned product not found in local product list.", "Scan Error");
            scannerStatusDiv.textContent = `Error: Product ID ${productId} not found locally.`;
            return false;
        }

        if (quantity > product.stock) {
            window.popupNotification.warning(`Only ${product.stock} units of ${product.name} available. Scanned: ${quantity}.`, "Stock Alert");
            scannerStatusDiv.textContent = `Stock issue for ${product.name}.`;
            return false;
        }
        
        const existingItem = cart.find(item => item.product_id === product.id);
        if (existingItem) {
            if (existingItem.quantity + quantity > product.stock) {
                window.popupNotification.warning(`Cannot add ${quantity} more. Total would exceed stock for ${product.name}.`, "Stock Alert");
                scannerStatusDiv.textContent = `Stock issue for ${product.name} (cart update).`;
                return false;
            }
            existingItem.quantity += quantity;
            existingItem.total = existingItem.quantity * existingItem.price;
        } else {
            cart.push({
                product_id: product.id,
                product_name: product.name,
                price: parseFloat(product.price),
                quantity: quantity,
                total: quantity * parseFloat(product.price)
            });
        }
        
        updateCartDisplay();
        productSearch.value = ''; // Clear search field after adding
        quantityInput.value = '1';
        window.popupNotification.success(`${product.name} (qty: ${quantity}) added to cart.`, "Product Added");
        scannerStatusDiv.textContent = `${product.name} added to cart via scanner.`;
        return true;
    }

    addToCartForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const productName = productSearch.value;
        const quantity = parseInt(quantityInput.value);
        
        if (!productName || quantity < 1) {
            window.popupNotification.warning("Please select a product and enter a valid quantity.");
            return;
        }
        
        const selectedOption = Array.from(productListDatalist.options).find(opt => opt.value === productName);
        if (!selectedOption) {
            window.popupNotification.warning("Product not found. Please select from the list or ensure it is scanned correctly.");
            return;
        }
        const productId = selectedOption.dataset.id;
        addProductToCartById(productId, quantity);
    });
    
    function updateCartDisplay() {
        const tbody = cartTable.querySelector('tbody');
        
        if (cart.length === 0) {
            emptyCartRow.style.display = 'table-row';
            generateBillBtn.disabled = true;
            grandTotalElement.textContent = '₹0.00';
        } else {
            emptyCartRow.style.display = 'none';
            generateBillBtn.disabled = false;
        }
        
        // Clear tbody except for the empty cart row
        const rowsToRemove = [];
        for (const child of tbody.children) {
            if (child !== emptyCartRow) {
                rowsToRemove.push(child);
            }
        }
        rowsToRemove.forEach(child => tbody.removeChild(child));
        
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
        grandTotalElement.textContent = `₹${grandTotal.toFixed(2)}`;
    }
    
    window.removeFromCart = function(index) {
        const item = cart[index];
        cart.splice(index, 1);
        updateCartDisplay();
        window.popupNotification.info(`${item.product_name} removed from cart.`);
    };
    
    generateBillBtn.addEventListener('click', async () => {
        if (cart.length === 0) {
            window.popupNotification.warning("Cart is empty. Add products to generate a bill.");
            return;
        }
        
        try {
            const response = await fetch('/billing/server.php?action=generateBill', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items: cart })
            });
            const result = await response.json();
            
            if (result.success) {
                window.popupNotification.success("Bill generated successfully!", "Success");
                const totalAmount = cart.reduce((sum, item) => sum + item.total, 0).toFixed(2);
                window.confirmNotification(
                    `<h3>Bill #${result.bill_id} Generated</h3>
                    <p>Date: ${new Date().toLocaleString()}</p>
                    <p>Total Items: ${cart.length}</p>
                    <p>Total Amount: ₹${totalAmount}</p>
                    <p>Thank you for your purchase!</p>`,
                    function() {
                        cart = [];
                        updateCartDisplay();
                        // Stop polling if a bill is generated to allow new pairing
                        if (pairingPollInterval) clearInterval(pairingPollInterval);
                        pairingInfoDiv.style.display = 'none';
                        pairingQrCodeDiv.innerHTML = '';
                        currentPairingId = null;
                        setupScannerBtn.disabled = false;
                        scannerStatusDiv.textContent = "Pairing session ended.";
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

    // Pairing Logic
    setupScannerBtn.addEventListener('click', async () => {
        setupScannerBtn.disabled = true;
        scannerStatusDiv.textContent = "Requesting pairing ID...";
        try {
            const response = await fetch('/billing/server.php?action=requestPairingId', { method: 'POST' });
            const result = await response.json();

            if (result.success && result.pairing_id) {
                currentPairingId = result.pairing_id;
                pairingIdDisplay.textContent = currentPairingId;
                pairingInfoDiv.style.display = 'block';
                scannerStatusDiv.textContent = "Pairing ID received. Waiting for mobile connection...";

                pairingQrCodeDiv.innerHTML = ''; // Clear previous QR
                if (typeof QRCode !== 'undefined') {
                     qrCodeInstance = new QRCode(pairingQrCodeDiv, {
                        text: currentPairingId,
                        width: 128,
                        height: 128,
                        colorDark : "#000000",
                        colorLight : "#ffffff",
                        correctLevel : QRCode.CorrectLevel.H
                    });
                } else {
                    pairingQrCodeDiv.textContent = "QR Code library not loaded.";
                }
                startPollingForScannedItems();
            } else {
                window.popupNotification.error(result.message || "Failed to get pairing ID.", "Pairing Error");
                scannerStatusDiv.textContent = `Error: ${result.message || "Failed to get pairing ID."}`;
                setupScannerBtn.disabled = false;
            }
        } catch (error) {
            console.error("Error requesting pairing ID:", error);
            window.popupNotification.error("Server error while requesting pairing ID.", "Pairing Error");
            scannerStatusDiv.textContent = "Server error during pairing setup.";
            setupScannerBtn.disabled = false;
        }
    });

    function startPollingForScannedItems() {
        if (pairingPollInterval) clearInterval(pairingPollInterval); // Clear existing interval

        pairingPollInterval = setInterval(async () => {
            if (!currentPairingId) {
                clearInterval(pairingPollInterval);
                return;
            }
            try {
                const response = await fetch(`/billing/server.php?action=getScannedItems&pairing_id=${currentPairingId}`);
                const result = await response.json();

                if (result.success && result.items && result.items.length > 0) {
                    result.items.forEach(item => {
                        // Assuming item is an object like { product_id: "...", quantity: 1 (default) }
                        // For now, server sends array of product_ids, default quantity 1
                        // Modify if server sends quantity too
                        const productId = item.product_id || item; // if item is just product_id string
                        const quantity = item.quantity || 1; 
                        addProductToCartById(productId, quantity);
                    });
                } else if (!result.success && result.message.includes("expired")) {
                    window.popupNotification.error("Pairing session expired. Please set up a new one.", "Pairing Expired");
                    clearInterval(pairingPollInterval);
                    pairingInfoDiv.style.display = 'none';
                    pairingQrCodeDiv.innerHTML = '';
                    currentPairingId = null;
                    setupScannerBtn.disabled = false;
                    scannerStatusDiv.textContent = "Pairing session expired.";
                }
                 // Optionally update status even if no items: scannerStatusDiv.textContent = "Polling for scanned items...";
            } catch (error) {
                console.warn("Polling error:", error);
                // scannerStatusDiv.textContent = "Polling error. Retrying...";
                // Don't stop polling on network errors, let it retry.
            }
        }, 3000); // Poll every 3 seconds
    }

</script>

<?php
// Include footer
require_once '../includes/footer.php';
?>