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
$pageTitle = "Supermarket Billing (POS)";
$bodyClass = "staff-page";

// Page-specific scripts
$pageScripts = [
    '/billing/js/qrcode.min.js' // For generating QR code for pairing URL
];

// Include header
require_once '../includes/header.php';

// Determine scheme (http or https) for generating full URL
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$baseScannerUrl = $scheme . '://' . $host . '/billing/staff/index.html'; // Path to the mobile scanner page

?>

<h1 class="page-title">Supermarket Billing (POS)</h1>

<!-- Mobile Scanner Pairing Section -->
<section class="content-section glass">
    <h2 class="section-title">Pair Mobile Scanner</h2>
    <div id="pairingUi" class="flex flex-col md:flex-row gap-2 items-start">
        <div class="flex-grow">
            <button id="setupScannerBtn" class="btn">Setup Mobile Scanner</button>
            <div id="pairingInfo" class="mt-2" style="display: none;">
                <p>Scan the QR code with the mobile device or manually open: <br><code id="scannerUrlDisplay" class="text-xs" style="word-break: break-all;"></code></p>
                <p class="text-sm text-secondary">Pairing ID: <strong id="pairingIdDisplay" class="text-lg"></strong> (Valid for 15 minutes)</p>
            </div>
        </div>
        <div id="pairingQrCode" class="mt-2 md:mt-0" style="min-width: 160px; min-height: 160px; background: white; padding: 10px; border-radius: var(--border-radius-sm);">
            <!-- QR Code will be rendered here -->
        </div>
    </div>
    <div id="scannerStatus" class="mt-2 text-sm"></div>
</section>

<section class="content-section glass mt-4">
    <h2 class="section-title">Add Products to Cart</h2>
    <form id="addToCartForm" autocomplete="off">
        <div class="flex flex-col gap-2 md:flex-row"> 
            <div class="form-group flex-grow mb-0"> 
                <label for="productSearch" class="sr-only">Search Product</label>
                <input type="text" id="productSearch" placeholder="Search product by name or scan directly..." required list="productListDatalist" class="w-full">
                <datalist id="productListDatalist"></datalist>
            </div>
            <div class="form-group w-full md:w-auto mb-0"> 
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
    let products = [];
    let cart = [];
    let currentPairingId = null;
    let pairingPollInterval = null;
    const baseScannerUrl = '<?php echo $baseScannerUrl; ?>';

    const productSearch = document.getElementById('productSearch');
    const productListDatalist = document.getElementById('productListDatalist');
    const quantityInput = document.getElementById('quantityInput');
    const addToCartForm = document.getElementById('addToCartForm');
    const cartTable = document.getElementById('cartTable');
    const emptyCartRow = document.getElementById('emptyCartRow');
    const grandTotalElement = document.getElementById('grandTotal');
    const generateBillBtn = document.getElementById('generateBillBtn');
    const setupScannerBtn = document.getElementById('setupScannerBtn');
    const pairingInfoDiv = document.getElementById('pairingInfo');
    const pairingIdDisplay = document.getElementById('pairingIdDisplay');
    const scannerUrlDisplay = document.getElementById('scannerUrlDisplay');
    const pairingQrCodeDiv = document.getElementById('pairingQrCode');
    const scannerStatusDiv = document.getElementById('scannerStatus');
    let qrCodeInstance = null;

    document.addEventListener('DOMContentLoaded', async () => {
        try {
            const response = await fetch('/billing/server.php?action=getProducts');
            const rawProducts = await response.json();
            products = rawProducts.map(product => ({
                id: product._id.$oid || product.id,
                name: product.name,
                price: parseFloat(product.price),
                stock: parseInt(product.stock)
            }));
            productListDatalist.innerHTML = products.map(p => `<option value="${p.name}" data-id="${p.id}" data-price="${p.price}" data-stock="${p.stock}">`).join('');
            updateCartDisplay();
        } catch (error) {
            console.error("Failed to fetch products:", error);
            window.popupNotification.error("Failed to load products.", "Data Error");
        }
    });

    function addProductToCartById(productId, quantity = 1, productName = null, productPrice = null) {
        let product = products.find(p => p.id === productId);

        if (!product && productName && productPrice !== null) {
            // Product might have been added to DB after this POS page loaded.
            // Use details from scanned item if available, but show a warning to refresh product list.
            product = { id: productId, name: productName, price: parseFloat(productPrice), stock: Infinity }; // Assume stock ok for now
            window.popupNotification.info(`Product '${productName}' added from scan. Local product list might be outdated.`, "Product Info");
        } else if (!product) {
            window.popupNotification.warning(`Product ID ${productId} not found in local list.`, "Scan Error");
            scannerStatusDiv.textContent = `Error: Product ID ${productId} not found locally.`;
            return false;
        }

        // Re-fetch product details for stock check to be sure, especially if it was from scan
        fetch(`/billing/server.php?action=getProduct&id=${productId}`)
            .then(res => res.json())
            .then(liveProductData => {
                if (!liveProductData) {
                    window.popupNotification.error(`Could not verify stock for ${product.name}. Product may have been removed.`, "Stock Error");
                    return false;
                }
                const currentStock = parseInt(liveProductData.stock);

                if (quantity > currentStock) {
                    window.popupNotification.warning(`Only ${currentStock} units of ${product.name} available. Scanned/Requested: ${quantity}.`, "Stock Alert");
                    scannerStatusDiv.textContent = `Stock issue for ${product.name}.`;
                    return false;
                }
                
                const existingItem = cart.find(item => item.product_id === product.id);
                if (existingItem) {
                    if (existingItem.quantity + quantity > currentStock) {
                        window.popupNotification.warning(`Cannot add ${quantity} more. Total would exceed stock for ${product.name}.`, "Stock Alert");
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
                productSearch.value = ''; 
                quantityInput.value = '1';
                window.popupNotification.success(`${product.name} (qty: ${quantity}) added to cart.`, "Product Added");
                scannerStatusDiv.textContent = `${product.name} added to cart.`;
            })
            .catch(err => {
                console.error("Error fetching live product data for stock check:", err);
                window.popupNotification.error("Could not verify product stock. Please try adding manually.", "Network Error");
            });
        return true; 
    }


    addToCartForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const productNameInput = productSearch.value;
        const quantity = parseInt(quantityInput.value);
        if (!productNameInput || quantity < 1) {
            window.popupNotification.warning("Please select a product and enter a valid quantity.");
            return;
        }
        const selectedOption = Array.from(productListDatalist.options).find(opt => opt.value === productNameInput);
        if (!selectedOption) {
            window.popupNotification.warning("Product not found. Please select from the list.");
            return;
        }
        addProductToCartById(selectedOption.dataset.id, quantity, selectedOption.value, parseFloat(selectedOption.dataset.price));
    });
    
    function updateCartDisplay() {
        const tbody = cartTable.querySelector('tbody');
        emptyCartRow.style.display = cart.length === 0 ? 'table-row' : 'none';
        generateBillBtn.disabled = cart.length === 0;
        
        const rowsToRemove = Array.from(tbody.children).filter(child => child !== emptyCartRow);
        rowsToRemove.forEach(child => tbody.removeChild(child));
        
        let grandTotal = 0;
        cart.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.product_name}</td>
                <td>₹${item.price.toFixed(2)}</td>
                <td>${item.quantity}</td>
                <td>₹${item.total.toFixed(2)}</td>
                <td><button class="btn" style="padding: 0.3rem 0.6rem; background: linear-gradient(135deg, #ef4444, #f43f5e);" onclick="removeFromCart(${index})">Remove</button></td>
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
            window.popupNotification.warning("Cart is empty.");
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
                    `<h3>Bill #${result.bill_id} Generated</h3><p>Total Amount: ₹${totalAmount}</p><p>Thank you!</p>`,
                    () => { // onConfirm
                        cart = [];
                        updateCartDisplay();
                        if (pairingPollInterval) clearInterval(pairingPollInterval);
                        pairingInfoDiv.style.display = 'none';
                        pairingQrCodeDiv.innerHTML = '';
                        currentPairingId = null;
                        setupScannerBtn.disabled = false;
                        scannerStatusDiv.textContent = "Pairing session ended.";
                    }
                );
            } else {
                window.popupNotification.error("Failed to generate bill: " + (result.message || "Unknown error"), "Error");
            }
        } catch (error) {
            console.error("Error generating bill:", error);
            window.popupNotification.error("An error occurred. Please try again.", "Error");
        }
    });

    setupScannerBtn.addEventListener('click', async () => {
        setupScannerBtn.disabled = true;
        scannerStatusDiv.textContent = "Requesting pairing ID...";
        try {
            const formData = new FormData();
            formData.append('action', 'requestPairingId');
            const response = await fetch('/billing/server.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success && result.pairing_id) {
                currentPairingId = result.pairing_id;
                const fullScannerUrl = `${baseScannerUrl}?pairing_id=${currentPairingId}`;
                
                pairingIdDisplay.textContent = currentPairingId;
                scannerUrlDisplay.textContent = fullScannerUrl;
                pairingInfoDiv.style.display = 'block';
                scannerStatusDiv.textContent = "Pairing ID received. Waiting for mobile connection...";

                pairingQrCodeDiv.innerHTML = ''; 
                if (typeof QRCode !== 'undefined') {
                     qrCodeInstance = new QRCode(pairingQrCodeDiv, {
                        text: fullScannerUrl,
                        width: 150,
                        height: 150,
                        colorDark : "#000000",
                        colorLight : "#ffffff",
                        correctLevel : QRCode.CorrectLevel.M
                    });
                } else {
                    pairingQrCodeDiv.textContent = "QR Code library not loaded. Manual entry required.";
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
        if (pairingPollInterval) clearInterval(pairingPollInterval); 
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
                        addProductToCartById(item.product_id, item.quantity, item.product_name, item.price);
                    });
                } else if (result.success === false && result.message && result.message.toLowerCase().includes("expired")) {
                    window.popupNotification.error("Pairing session expired. Please set up a new one.", "Pairing Expired");
                    clearInterval(pairingPollInterval);
                    pairingInfoDiv.style.display = 'none';
                    pairingQrCodeDiv.innerHTML = '';
                    currentPairingId = null;
                    setupScannerBtn.disabled = false;
                    scannerStatusDiv.textContent = "Pairing session expired.";
                }
            } catch (error) {
                // console.warn("Polling error:", error); 
                // Don't stop polling for network errors, let it retry.
            }
        }, 2500); // Poll every 2.5 seconds
    }
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?>