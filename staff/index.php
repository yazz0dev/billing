<?php
session_start();

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['staff', 'admin'])) {
    header('Location: /billing/login.php?error=unauthorized');
    exit;
}

$pageTitle = "Supermarket Billing (POS)";
$bodyClass = "staff-page";
$pageScripts = ['/billing/js/qrcode.min.js'];

require_once '../includes/header.php';
?>

<h1 class="page-title">Supermarket Billing (POS)</h1>

<section class="content-section glass">
    <h2 class="section-title">Pair Mobile Scanner</h2>
    <p>User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
    <div id="pairingUiDesktop" class="flex flex-col md:flex-row gap-2 items-start">
        <div class="flex-grow">
            <button id="initiatePairingBtnDesktop" class="btn">Generate Pairing QR</button>
            <div id="pairingInstructionsDesktop" class="mt-2" style="display: none;">
                <p class="text-sm text-secondary">
                    On your logged-in mobile device, go to "Pair with Desktop" (or similar option) and scan the QR code below.
                    This QR code is valid for about 2-5 minutes.
                </p>
            </div>
        </div>
        <div id="pairingQrCodeDesktop" class="mt-2 md:mt-0" style="min-width: 180px; min-height: 180px; background: white; padding: 10px; border-radius: var(--border-radius-sm); display:none;">
            <!-- QR Code will be rendered here -->
        </div>
    </div>
    <div id="desktopScannerStatus" class="mt-2 text-sm"></div>
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
                    <tr><th>Product</th><th>Price</th><th>Quantity</th><th>Total</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <tr id="emptyCartRow"><td colspan="5" class="text-center">Cart is empty</td></tr>
                </tbody>
                <tfoot>
                    <tr><td colspan="3" class="text-right"><strong>Grand Total:</strong></td><td id="grandTotal">₹0.00</td><td></td></tr>
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
    let desktopPairingPollInterval = null;
    let activePairingTokenForQR = null; // Store the token used to generate the current QR

    const productSearch = document.getElementById('productSearch');
    const productListDatalist = document.getElementById('productListDatalist');
    const quantityInput = document.getElementById('quantityInput');
    const addToCartForm = document.getElementById('addToCartForm');
    const cartTable = document.getElementById('cartTable');
    const emptyCartRow = document.getElementById('emptyCartRow');
    const grandTotalElement = document.getElementById('grandTotal');
    const generateBillBtn = document.getElementById('generateBillBtn');
    
    const initiatePairingBtnDesktop = document.getElementById('initiatePairingBtnDesktop');
    const pairingInstructionsDesktop = document.getElementById('pairingInstructionsDesktop');
    const pairingQrCodeDesktopDiv = document.getElementById('pairingQrCodeDesktop');
    const desktopScannerStatus = document.getElementById('desktopScannerStatus');
    let desktopQrCodeInstance = null;

    document.addEventListener('DOMContentLoaded', async () => {
        try {
            const response = await fetch('/billing/server.php?action=getProducts');
            const rawProducts = await response.json();
            products = rawProducts.map(product => ({
                id: product._id.$oid || product.id, name: product.name,
                price: parseFloat(product.price), stock: parseInt(product.stock)
            }));
            productListDatalist.innerHTML = products.map(p => `<option value="${p.name}" data-id="${p.id}" data-price="${p.price}" data-stock="${p.stock}">`).join('');
            updateCartDisplay();
        } catch (error) {
            console.error("Failed to fetch products:", error);
            window.popupNotification.error("Failed to load products.", "Data Error");
        }
        // Start polling for scanned items IF a pairing session is already active for this user
        checkInitialPairingAndStartPolling();
    });

    function addProductToCartById(productId, quantity = 1, productName = null, productPrice = null) {
        let product = products.find(p => p.id === productId);

        if (!product && productName && productPrice !== null) {
            product = { id: productId, name: productName, price: parseFloat(productPrice), stock: Infinity };
            window.popupNotification.info(`Product '${productName}' added from scan. Local product list might be outdated.`, "Product Info");
        } else if (!product) {
            window.popupNotification.warning(`Product ID ${productId} not found.`, "Scan Error");
            desktopScannerStatus.textContent = `Error: Product ID ${productId} not found.`;
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
                productSearch.value = ''; 
                quantityInput.value = '1';
                window.popupNotification.success(`${product.name} (qty: ${quantity}) added to cart.`, "Product Added");
                desktopScannerStatus.textContent = `${product.name} added.`;
            })
            .catch(err => {
                console.error("Error fetching product data for stock check:", err);
                window.popupNotification.error("Could not verify product stock. Try adding manually.", "Network Error");
            });
        return true;
    }

    addToCartForm.addEventListener('submit', (e) => {
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
                <td>${item.product_name}</td><td>₹${item.price.toFixed(2)}</td>
                <td>${item.quantity}</td><td>₹${item.total.toFixed(2)}</td>
                <td><button class="btn" style="padding:0.3rem 0.6rem; background:linear-gradient(135deg, #ef4444, #f43f5e);" onclick="removeFromCart(${index})">Remove</button></td>
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
        window.popupNotification.info(`${item.product_name} removed.`);
    };
    
    generateBillBtn.addEventListener('click', async () => {
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
                        if (desktopPairingPollInterval) clearInterval(desktopPairingPollInterval);
                        pairingInstructionsDesktop.style.display = 'none';
                        pairingQrCodeDesktopDiv.innerHTML = ''; pairingQrCodeDesktopDiv.style.display = 'none';
                        activePairingTokenForQR = null;
                        initiatePairingBtnDesktop.disabled = false;
                        desktopScannerStatus.textContent = "Pairing ended. Generate new QR if needed.";
                        // Also tell server to end this pairing session explicitly
                        fetch('/billing/server.php?action=endMobilePairing', { method: 'POST' }); 
                    }
                );
            } else { window.popupNotification.error("Bill generation failed: " + (result.message || "Error"), "Error"); }
        } catch (error) {
            console.error("Error generating bill:", error);
            window.popupNotification.error("Error generating bill. Try again.", "Error");
        }
    });

    initiatePairingBtnDesktop.addEventListener('click', async () => {
        initiatePairingBtnDesktop.disabled = true;
        desktopScannerStatus.textContent = "Requesting pairing token...";
        pairingQrCodeDesktopDiv.innerHTML = ''; 
        pairingQrCodeDesktopDiv.style.display = 'none';
        pairingInstructionsDesktop.style.display = 'none';

        try {
            const formData = new FormData(); // No data needed, server uses session
            const response = await fetch('/billing/server.php?action=initiateMobilePairing', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success && result.pairing_token) {
                activePairingTokenForQR = result.pairing_token;
                // Construct the URL for the mobile to open to confirm pairing
                const pairingUrlForMobile = `${window.location.origin}/billing/staff/mobile-pair.php?token=${activePairingTokenForQR}`;
                
                pairingInstructionsDesktop.style.display = 'block';
                pairingQrCodeDesktopDiv.style.display = 'block';
                desktopScannerStatus.textContent = "QR code generated. Scan with your mobile device.";

                if (typeof QRCode !== 'undefined') {
                     desktopQrCodeInstance = new QRCode(pairingQrCodeDesktopDiv, {
                        text: pairingUrlForMobile, width: 160, height: 160,
                        colorDark : "#000000", colorLight : "#ffffff",
                        correctLevel : QRCode.CorrectLevel.M
                    });
                } else {
                    pairingQrCodeDesktopDiv.textContent = "QR lib error.";
                }
                // Start polling for scanned items now that a pairing attempt is active
                startPollingForDesktopScannedItems(); 
            } else {
                window.popupNotification.error(result.message || "Failed to get pairing token.", "Pairing Error");
                desktopScannerStatus.textContent = `Error: ${result.message || "Failed to get token."}`;
                initiatePairingBtnDesktop.disabled = false;
            }
        } catch (error) {
            console.error("Error initiating pairing:", error);
            window.popupNotification.error("Server error during pairing initiation.", "Pairing Error");
            desktopScannerStatus.textContent = "Server error for pairing.";
            initiatePairingBtnDesktop.disabled = false;
        }
    });

    function startPollingForDesktopScannedItems() {
        if (desktopPairingPollInterval) clearInterval(desktopPairingPollInterval); 
        
        // This polling assumes the server uses the desktop's session to find its active pairing
        // and then retrieves items from that pairing session.
        desktopPairingPollInterval = setInterval(async () => {
            // No specific pairing ID needed from client; server uses session.
            try {
                const response = await fetch(`/billing/server.php?action=getScannedItems`); // No pairing_id in URL
                const result = await response.json();

                if (result.success && result.items && result.items.length > 0) {
                    result.items.forEach(item => {
                        addProductToCartById(item.product_id, item.quantity, item.product_name, item.price);
                    });
                    desktopScannerStatus.textContent = `${result.items.length} item(s) received from mobile.`;
                } else if (result.success === false && result.message) {
                    if(result.message.toLowerCase().includes("not paired") || result.message.toLowerCase().includes("expired")) {
                        desktopScannerStatus.textContent = `Mobile scanner not actively paired or session expired.`;
                        // Do not clear interval here if we expect mobile to re-pair to this token.
                        // QR code might still be valid.
                        // If QR token itself expires, then we might stop or prompt user.
                        // For now, let it continue polling, mobile might re-pair.
                        // If generateBill clears pairing, then this will stop making sense.
                    } else {
                        // desktopScannerStatus.textContent = `Status: ${result.message}`;
                    }
                } else if (result.success && result.is_paired === false) { // From checkMobilePairingStatus logic if reused
                     desktopScannerStatus.textContent = `Mobile scanner is not currently paired.`;
                }
            } catch (error) {
                // console.warn("Desktop polling error:", error); 
            }
        }, 3000); 
    }
    
    async function checkInitialPairingAndStartPolling() {
        try {
            const response = await fetch('/billing/server.php?action=checkDesktopPairingStatus'); // New action
            const data = await response.json();
            if (data.success && data.is_paired) {
                desktopScannerStatus.textContent = `Mobile scanner is active for user ${data.staff_username}. Polling for items...`;
                pairingInstructionsDesktop.style.display = 'none';
                pairingQrCodeDesktopDiv.style.display = 'none';
                initiatePairingBtnDesktop.disabled = true; // A session is already active
                startPollingForDesktopScannedItems();
            } else {
                desktopScannerStatus.textContent = data.message || `No active mobile pairing found. Click "Generate Pairing QR".`;
                initiatePairingBtnDesktop.disabled = false;
            }
        } catch (error) {
            desktopScannerStatus.textContent = 'Error checking initial pairing status.';
            initiatePairingBtnDesktop.disabled = false;
        }
    }
</script>

<?php
require_once '../includes/footer.php';
?>