<?php
session_start();

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['staff', 'admin'])) {
    header('Location: /billing/login.php?error=unauthorized');
    exit;
}

$pageTitle = "Supermarket Billing (POS)";
$bodyClass = "staff-page";
// $pageScripts = ['/billing/js/qrcode.min.js']; // QR code library no longer needed for this page

require_once '../includes/header.php';
?>

<h1 class="page-title">Supermarket Billing (POS)</h1>

<section class="content-section glass">
    <h2 class="section-title">Mobile Scanner Link</h2>
    <p>User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
    <div id="pairingUiDesktop" class="flex flex-col md:flex-row gap-2 items-start">
        <div class="flex-grow">
            <button id="activateScannerModeBtn" class="btn">Activate My Mobile as Scanner</button>
            <button id="deactivateScannerModeBtn" class="btn" style="display:none; background-color:var(--warning);">Deactivate Mobile Scanner</button>
            <div id="pairingInstructionsDesktop" class="mt-2">
                <p class="text-sm text-secondary">
                    Ensure you are logged in on your mobile device with the same account (<b><?php echo htmlspecialchars($_SESSION['username']); ?></b>).
                    Then, on your mobile, navigate to the "Mobile Scanner" page (usually found after login or in a simplified mobile menu).
                    It should automatically connect if this POS has activated scanner mode.
                </p>
            </div>
        </div>
    </div>
    <div id="desktopScannerStatus" class="mt-2 text-sm status-message info">Status: Idle. Click "Activate" to enable mobile scanning.</div>
</section>

<section class="content-section glass mt-4">
    <h2 class="section-title">Add Products to Cart</h2>
    <form id="addToCartForm" autocomplete="off">
        <div class="flex flex-col gap-2 md:flex-row"> 
            <div class="form-group flex-grow mb-0"> 
                <input type="text" id="productSearch" placeholder="Search product by name or scan directly..." required list="productListDatalist" class="w-full">
                <datalist id="productListDatalist"></datalist>
            </div>
            <div class="form-group w-full md:w-auto mb-0"> 
                <input type="number" id="quantityInput" placeholder="Qty" min="1" value="1" required class="w-full">
            </div>
            <button type="submit" class="btn w-full md:w-auto">Add to Cart</button>
        </div>
    </form>

    <div class="mt-4">
        <h3>Cart Items</h3>
        <div class="table-wrapper">
            <table id="cartTable">
                <thead><tr><th>Product</th><th>Price</th><th>Quantity</th><th>Total</th><th>Action</th></tr></thead>
                <tbody><tr id="emptyCartRow"><td colspan="5" class="text-center">Cart is empty</td></tr></tbody>
                <tfoot><tr><td colspan="3" class="text-right"><strong>Grand Total:</strong></td><td id="grandTotal">₹0.00</td><td></td></tr></tfoot>
            </table>
        </div>
        <div class="flex justify-end mt-4">
            <button id="generateBillBtn" class="btn" disabled>Generate Bill</button>
        </div>
    </div>
</section>

<script>
    let products = []; let cart = []; let desktopPairingPollInterval = null; let isDesktopScannerActive = false;

    const productSearch = document.getElementById('productSearch');
    const productListDatalist = document.getElementById('productListDatalist');
    const quantityInput = document.getElementById('quantityInput');
    const addToCartForm = document.getElementById('addToCartForm');
    const cartTable = document.getElementById('cartTable');
    const emptyCartRow = document.getElementById('emptyCartRow');
    const grandTotalElement = document.getElementById('grandTotal');
    const generateBillBtn = document.getElementById('generateBillBtn');
    
    const activateScannerModeBtn = document.getElementById('activateScannerModeBtn');
    const deactivateScannerModeBtn = document.getElementById('deactivateScannerModeBtn');
    const desktopScannerStatus = document.getElementById('desktopScannerStatus');

    function displayDesktopStatus(message, type = 'info') {
        desktopScannerStatus.textContent = message;
        desktopScannerStatus.className = `mt-2 text-sm status-message ${type}`;
    }

    document.addEventListener('DOMContentLoaded', async () => {
        try {
            const response = await fetch('/billing/server.php?action=getProducts');
            products = (await response.json()).map(p => ({ id: p._id.$oid || p.id, name: p.name, price: parseFloat(p.price), stock: parseInt(p.stock) }));
            productListDatalist.innerHTML = products.map(p => `<option value="${p.name}" data-id="${p.id}" data-price="${p.price}" data-stock="${p.stock}">`).join('');
            updateCartDisplay();
        } catch (error) { console.error("Err fetching products:", error); window.popupNotification.error("Failed to load products.", "Data Error"); }
        checkInitialDesktopScannerStatus();
    });

    function addProductToCartById(productId, quantity = 1, productName = null, productPrice = null) {
        // ... (Keep this function largely the same as your previous version for adding to cart) ...
        // (Ensure it handles stock checks and updates UI correctly)
        let product = products.find(p => p.id === productId);

        if (!product && productName && productPrice !== null) {
            product = { id: productId, name: productName, price: parseFloat(productPrice), stock: Infinity };
            window.popupNotification.info(`Product '${productName}' added from scan. Local list might be old.`, "Product Info");
        } else if (!product) {
            window.popupNotification.warning(`Product ID ${productId} not found.`, "Scan Error");
            displayDesktopStatus(`Error: Product ID ${productId} not found.`, 'error');
            return false;
        }

        fetch(`/billing/server.php?action=getProduct&id=${productId}`)
            .then(res => res.json())
            .then(liveProductData => {
                if (!liveProductData || typeof liveProductData.stock === 'undefined') {
                    window.popupNotification.error(`Could not verify stock for ${product.name}.`, "Stock Error");
                    return false;
                }
                const currentStock = parseInt(liveProductData.stock);

                if (quantity > currentStock) {
                    window.popupNotification.warning(`Only ${currentStock} of ${product.name} available. Requested: ${quantity}.`, "Stock Alert");
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
                        product_id: product.id, product_name: product.name,
                        price: parseFloat(product.price), quantity: quantity,
                        total: quantity * parseFloat(product.price)
                    });
                }
                updateCartDisplay();
                productSearch.value = ''; quantityInput.value = '1';
                window.popupNotification.success(`${product.name} (qty: ${quantity}) added to cart.`, "Product Added");
                displayDesktopStatus(`${product.name} added to cart via mobile.`, 'success');
            })
            .catch(err => { console.error("Stock check error:", err); window.popupNotification.error("Stock check failed.", "Network Error"); });
        return true;
    }

    addToCartForm.addEventListener('submit', (e) => { /* ... (Keep this function same) ... */ 
        e.preventDefault();
        const productNameInput = productSearch.value;
        const quantityVal = parseInt(quantityInput.value);
        if (!productNameInput || quantityVal < 1) {
            window.popupNotification.warning("Select product and enter valid quantity."); return;
        }
        const selectedOption = Array.from(productListDatalist.options).find(opt => opt.value === productNameInput);
        if (!selectedOption) {
            window.popupNotification.warning("Product not found."); return;
        }
        addProductToCartById(selectedOption.dataset.id, quantityVal, selectedOption.value, parseFloat(selectedOption.dataset.price));
    });
    function updateCartDisplay() { /* ... (Keep this function same) ... */ 
        const tbody = cartTable.querySelector('tbody');
        emptyCartRow.style.display = cart.length === 0 ? 'table-row' : 'none';
        generateBillBtn.disabled = cart.length === 0;
        
        const rowsToRemove = Array.from(tbody.children).filter(child => child !== emptyCartRow);
        rowsToRemove.forEach(child => tbody.removeChild(child));
        
        let grandTotal = 0;
        cart.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.product_name}</td><td>₹${item.price.toFixed(2)}</td>
                <td>${item.quantity}</td><td>₹${item.total.toFixed(2)}</td>
                <td><button class="btn" style="padding:0.3rem 0.6rem; background:linear-gradient(135deg, #ef4444, #f43f5e);" onclick="removeFromCart(${index})">Remove</button></td>
            `;
            tbody.appendChild(tr);
            grandTotal += item.total;
        });
        grandTotalElement.textContent = `₹${grandTotal.toFixed(2)}`;
    }
    window.removeFromCart = function(index) { /* ... (Keep this function same) ... */
        const item = cart[index];
        cart.splice(index, 1);
        updateCartDisplay();
        window.popupNotification.info(`${item.product_name} removed.`);
    };
    generateBillBtn.addEventListener('click', async () => { /* ... (Keep this function same, BUT in onConfirm for bill, call deactivateScannerModeFromServer()) ... */
        if (cart.length === 0) { window.popupNotification.warning("Cart is empty."); return; }
        try {
            const response = await fetch('/billing/server.php?action=generateBill', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items: cart })
            });
            const result = await response.json();
            if (result.success) {
                window.popupNotification.success("Bill generated!", "Success");
                const totalAmount = cart.reduce((sum, item) => sum + item.total, 0).toFixed(2);
                window.confirmNotification(
                    `<h3>Bill #${result.bill_id}</h3><p>Amount: ₹${totalAmount}</p><p>Thank you!</p>`,
                    () => { // onConfirm
                        cart = []; updateCartDisplay();
                        deactivateScannerModeFromServer(true); // true to indicate bill generation caused deactivation
                    }
                );
            } else { window.popupNotification.error("Bill generation failed: " + (result.message || "Error"), "Error"); }
        } catch (error) { console.error("Error generating bill:", error); window.popupNotification.error("Error generating bill. Try again.", "Error"); }
    });

    activateScannerModeBtn.addEventListener('click', async () => {
        activateScannerModeBtn.disabled = true;
        deactivateScannerModeBtn.style.display = 'none';
        displayDesktopStatus("Activating mobile scanner mode...", 'info');
        try {
            const response = await fetch('/billing/server.php?action=activateMobileScanning', { method: 'POST' }); // New action
            const result = await response.json();
            if (result.success) {
                isDesktopScannerActive = true;
                displayDesktopStatus(`Mobile scanner mode activated for ${result.staff_username || 'you'}. Mobile can now connect & scan.`, 'success');
                activateScannerModeBtn.style.display = 'none';
                deactivateScannerModeBtn.style.display = 'inline-block';
                startPollingForScannedItemsDesktop();
            } else {
                displayDesktopStatus(`Failed to activate: ${result.message || 'Unknown error'}`, 'error');
                activateScannerModeBtn.disabled = false;
            }
        } catch (error) {
            displayDesktopStatus("Error activating scanner mode. Check network.", 'error');
            activateScannerModeBtn.disabled = false;
        }
    });

    deactivateScannerModeBtn.addEventListener('click', () => deactivateScannerModeFromServer(false));

    async function deactivateScannerModeFromServer(calledAfterBill) {
        if (desktopPairingPollInterval) clearInterval(desktopPairingPollInterval);
        desktopPairingPollInterval = null;
        isDesktopScannerActive = false;
        
        activateScannerModeBtn.style.display = 'inline-block';
        activateScannerModeBtn.disabled = false;
        deactivateScannerModeBtn.style.display = 'none';
        if (!calledAfterBill) { // If not called after bill, means user manually deactivated
             displayDesktopStatus("Mobile scanner mode deactivated by POS.", 'info');
        } else {
             displayDesktopStatus("Pairing ended due to bill generation. Activate again if needed.", 'info');
        }


        try {
            await fetch('/billing/server.php?action=deactivateMobileScanning', { method: 'POST' }); // New action
        } catch (error) { console.error("Error deactivating on server:", error); }
    }

    function startPollingForScannedItemsDesktop() {
        if (desktopPairingPollInterval) clearInterval(desktopPairingPollInterval);
        desktopPairingPollInterval = setInterval(async () => {
            if (!isDesktopScannerActive) { // Stop polling if master switch is off
                 clearInterval(desktopPairingPollInterval); desktopPairingPollInterval = null; return;
            }
            try {
                const response = await fetch(`/billing/server.php?action=getScannedItems`);
                const result = await response.json();
                if (result.success && result.items && result.items.length > 0) {
                    result.items.forEach(item => {
                        addProductToCartById(item.product_id, item.quantity, item.product_name, item.price);
                    });
                } else if (result.success === false && result.message && result.message.toLowerCase().includes("no active mobile pairing")) {
                    displayDesktopStatus("Waiting for mobile to connect/scan...", 'info');
                } else if (result.success === false) {
                     displayDesktopStatus(`Status: ${result.message || 'Polling...'}`, 'info');
                }
            } catch (error) { /* console.warn("Desktop polling error:", error); */ }
        }, 2500);
    }
    
    async function checkInitialDesktopScannerStatus() {
        try {
            const response = await fetch('/billing/server.php?action=checkDesktopScannerActivation'); // New Action
            const data = await response.json();
            if (data.success && data.is_active) {
                isDesktopScannerActive = true;
                displayDesktopStatus(`Mobile scanner mode is ALREADY ACTIVE for ${data.staff_username || 'you'}. Polling for items...`, 'success');
                activateScannerModeBtn.style.display = 'none';
                deactivateScannerModeBtn.style.display = 'inline-block';
                startPollingForScannedItemsDesktop();
            } else {
                isDesktopScannerActive = false;
                displayDesktopStatus(data.message || "Scanner mode is not active. Click 'Activate'.", 'info');
                activateScannerModeBtn.style.display = 'inline-block';
                deactivateScannerModeBtn.style.display = 'none';
            }
        } catch (error) {
            displayDesktopStatus('Error checking initial scanner status.', 'error');
        }
    }
</script>

<?php
require_once '../includes/footer.php';
?>