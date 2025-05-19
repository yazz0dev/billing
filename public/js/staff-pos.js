// public/js/staff-pos.js
document.addEventListener('DOMContentLoaded', async () => {
    const productSearch = document.getElementById('productSearch');
    const productListDatalist = document.getElementById('productListDatalist');
    const quantityInput = document.getElementById('quantityInput');
    const addToCartForm = document.getElementById('addToCartForm');
    const cartTableBody = document.getElementById('cartTable')?.querySelector('tbody');
    const emptyCartRow = document.getElementById('emptyCartRow');
    const grandTotalElement = document.getElementById('grandTotal');
    const generateBillBtn = document.getElementById('generateBillBtn');
    const activateScannerModeBtn = document.getElementById('activateScannerModeBtn');
    const deactivateScannerModeBtn = document.getElementById('deactivateScannerModeBtn');
    const desktopScannerStatus = document.getElementById('desktopScannerStatus');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    let cart = [];
    let productsData = []; // From datalist
    let desktopPairingPollInterval = null;
    let isDesktopScannerActive = false;

    function displayDesktopStatus(message, type = 'info') {
        if (!desktopScannerStatus) return;
        desktopScannerStatus.textContent = message;
        desktopScannerStatus.className = `mt-2 text-sm status-message ${type}`;
    }

    function updateCartDisplay() { /* ... (same as original, ensure it uses global handleRemoveFromCart) ... */ }
    window.handleRemoveFromCart = function(index) { /* ... (same as original) ... */ };
    
    async function addProductToCartById(productId, quantity = 1, productNameFromScan = null, productPriceFromScan = null) {
        let productInfo = productsData.find(p => p.id === productId);

        // If details came from scan and not in local list, use scanned details.
        // This is less secure as price is client-provided; server should always validate price on bill generation.
        if (!productInfo && productNameFromScan && productPriceFromScan !== null) {
            productInfo = { id: productId, name: productNameFromScan, price: parseFloat(productPriceFromScan) };
            if(window.popupNotification) window.popupNotification.info(`Product '${productNameFromScan}' added from scan. Price will be verified.`, "Product Info");
        } else if (!productInfo) {
            if(window.popupNotification) window.popupNotification.warning(`Product ID ${productId} not found in local list.`, "Cart Error");
            return false;
        }
        
        // Fetch live stock before adding to cart visually
        try {
            const stockCheckResponse = await fetch(`${window.APP_URL}/api/products/${productId}`,{
                 headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrfToken && {'X-CSRF-TOKEN': csrfToken}) }
            });
            if (!stockCheckResponse.ok) throw new Error('Product not found on server for stock check.');
            const stockCheckResult = await stockCheckResponse.json();
            
            if (!stockCheckResult.success || typeof stockCheckResult.data.stock === 'undefined') {
                if(window.popupNotification) window.popupNotification.error(`Could not verify stock for ${productInfo.name}.`, "Stock Error");
                return false;
            }
            const currentStock = parseInt(stockCheckResult.data.stock);

            const existingItem = cart.find(item => item.product_id === productInfo.id);
            const quantityToAdd = quantity;
            const totalQuantityInCart = (existingItem ? existingItem.quantity : 0) + quantityToAdd;

            if (totalQuantityInCart > currentStock) {
                if(window.popupNotification) window.popupNotification.warning(`Only ${currentStock} of ${productInfo.name} available. Cannot add ${quantityToAdd}.`, "Stock Alert");
                return false;
            }
            
            if (existingItem) {
                existingItem.quantity += quantityToAdd;
                existingItem.total = existingItem.quantity * existingItem.price; // Price used is from initial add
            } else {
                cart.push({
                    product_id: productInfo.id,
                    product_name: productInfo.name,
                    price: parseFloat(productInfo.price), // Price from datalist/scan (should be verified by server later)
                    quantity: quantityToAdd,
                    total: quantityToAdd * parseFloat(productInfo.price)
                });
            }
            updateCartDisplay();
            if (productSearch) productSearch.value = '';
            if (quantityInput) quantityInput.value = '1';
            if (window.popupNotification) window.popupNotification.success(`${productInfo.name} (qty: ${quantityToAdd}) added to cart.`, "Cart Update");
            displayDesktopStatus(`${productInfo.name} added to cart via manual/scan.`, 'success');
            return true;

        } catch (error) {
            console.error("Stock check or add to cart error:", error);
            if(window.popupNotification) window.popupNotification.error("Error adding to cart: " + error.message, "Cart Error");
            return false;
        }
    }

    if (addToCartForm) {
        addToCartForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const productNameInput = productSearch.value;
            const quantityVal = parseInt(quantityInput.value);

            if (!productNameInput || quantityVal < 1) { /* ... */ return; }
            const selectedOption = Array.from(productListDatalist.options).find(opt => opt.value === productNameInput);
            if (!selectedOption || !selectedOption.dataset.id) { /* ... */ return; }
            addProductToCartById(selectedOption.dataset.id, quantityVal); // Removed name/price here, they are in productsData
        });
    }

    if (generateBillBtn) {
        generateBillBtn.addEventListener('click', async () => {
            if (cart.length === 0) { /* ... */ return; }
            try {
                const response = await fetch(`${window.APP_URL}/api/bills/generate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        ...(csrfToken && {'X-CSRF-TOKEN': csrfToken})
                    },
                    body: JSON.stringify({ items: cart }) // Server will use its own product prices
                });
                const result = await response.json();
                if (response.ok && result.success) {
                    if(window.popupNotification) window.popupNotification.success("Bill generated successfully!", "Bill Generated");
                    if(window.confirmNotification) {
                        window.confirmNotification(
                            `<h3>Bill #${result.bill_id}</h3><p>Amount: ₹${parseFloat(result.total_amount).toFixed(2)}</p><p>Thank you!</p>`,
                            () => { cart = []; updateCartDisplay(); if (isDesktopScannerActive) deactivateScannerModeFromServer(true); },
                            () => { cart = []; updateCartDisplay(); if (isDesktopScannerActive) deactivateScannerModeFromServer(true); },
                            { title: "Bill Generated", confirmText: "New Bill", cancelText:"Close" }
                        );
                    } else { /* fallback alert */ }
                } else {
                    let errorMsg = result.message || "Bill generation failed";
                    if (result.errors) errorMsg = Object.values(result.errors).flat().join(' ');
                    if(window.popupNotification) window.popupNotification.error(errorMsg);
                }
            } catch (error) { /* ... */ }
        });
    }

    async function activateScannerModeOnServer() {
        if (!activateScannerModeBtn || !deactivateScannerModeBtn) return;
        activateScannerModeBtn.disabled = true;
        displayDesktopStatus("Activating mobile scanner mode...", 'info');
        try {
            const response = await fetch(`${window.APP_URL}/api/scanner/activate-pos`, {
                 method: 'POST',
                 headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', ...(csrfToken && {'X-CSRF-TOKEN': csrfToken}) }
            });
            const result = await response.json();
            if (response.ok && result.success) {
                isDesktopScannerActive = true;
                displayDesktopStatus(`Scanner mode activated for ${result.staff_username || 'you'}. Mobile can now connect.`, 'success');
                activateScannerModeBtn.style.display = 'none';
                deactivateScannerModeBtn.style.display = 'inline-block';
                startPollingForScannedItemsDesktop();
            } else { /* ... */ }
        } catch (error) { /* ... */ }
    }
    if(activateScannerModeBtn) activateScannerModeBtn.addEventListener('click', activateScannerModeOnServer);

    async function deactivateScannerModeFromServer(calledAfterBill = false) {
        if (!activateScannerModeBtn || !deactivateScannerModeBtn) return;
        if (desktopPairingPollInterval) clearInterval(desktopPairingPollInterval);
        desktopPairingPollInterval = null;
        isDesktopScannerActive = false;
        
        activateScannerModeBtn.style.display = 'inline-block';
        activateScannerModeBtn.disabled = false;
        deactivateScannerModeBtn.style.display = 'none';
        const statusMsg = calledAfterBill ? "Pairing ended due to bill generation." : "Mobile scanner mode deactivated by POS.";
        displayDesktopStatus(statusMsg, 'info');

        try {
            await fetch(`${window.APP_URL}/api/scanner/deactivate-pos`, {
                method: 'POST',
                 headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', ...(csrfToken && {'X-CSRF-TOKEN': csrfToken}) }
            });
        } catch (error) { /* ... */ }
    }
    if(deactivateScannerModeBtn) deactivateScannerModeBtn.addEventListener('click', () => deactivateScannerModeFromServer(false));

    function startPollingForScannedItemsDesktop() {
        if (desktopPairingPollInterval) clearInterval(desktopPairingPollInterval);
        desktopPairingPollInterval = setInterval(async () => {
            if (!isDesktopScannerActive) { /* ... */ return; }
            try {
                const response = await fetch(`${window.APP_URL}/api/scanner/items`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrfToken && {'X-CSRF-TOKEN': csrfToken}) }
                });
                const result = await response.json();
                if (result.success && result.items && result.items.length > 0) {
                    result.items.forEach(item => {
                        addProductToCartById(item.product_id, item.quantity, item.product_name, item.price);
                    });
                } else if (result.success === false && result.message && desktopScannerStatus) {
                     displayDesktopStatus(`Scanner: ${result.message}`, result.is_active ? 'info' : 'warning');
                     if (!result.is_active && result.message.toLowerCase().includes("not active")) { // If server explicitly says not active
                        if (isDesktopScannerActive) deactivateScannerModeFromServer(false); // Sync UI
                     }
                } else if (result.success === true && result.items.length === 0 && result.message && desktopScannerStatus) {
                    // e.g. "Waiting for mobile scanner to connect."
                    displayDesktopStatus(`Scanner: ${result.message}`, 'info');
                }
            } catch (error) { /* console.warn ... */ }
        }, 2500);
    }
    
    async function checkInitialDesktopScannerStatus() {
        if (!activateScannerModeBtn || !deactivateScannerModeBtn) return;
        try {
            const response = await fetch(`${window.APP_URL}/api/scanner/check-pos-activation`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrfToken && {'X-CSRF-TOKEN': csrfToken}) }
            });
            const data = await response.json();
            if (data.success && data.is_active) {
                isDesktopScannerActive = true;
                displayDesktopStatus(`Scanner mode is ALREADY ACTIVE for ${data.staff_username || 'you'}. ${data.message}`, 'success');
                activateScannerModeBtn.style.display = 'none';
                deactivateScannerModeBtn.style.display = 'inline-block';
                startPollingForScannedItemsDesktop();
            } else { /* ... */ }
        } catch (error) { /* ... */ }
    }

    function initializePOS() {
        if (productListDatalist) {
            productsData = Array.from(productListDatalist.options).map(opt => ({
                id: opt.dataset.id, name: opt.value, price: parseFloat(opt.dataset.price), stock: parseInt(opt.dataset.stock)
            }));
        }
        updateCartDisplay();
        checkInitialDesktopScannerStatus();
    }
    initializePOS();
});