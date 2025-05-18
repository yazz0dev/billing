// public/js/staff-pos.js
document.addEventListener('DOMContentLoaded', async () => {
    // --- Element Selectors ---
    const productSearch = document.getElementById('productSearch');
    const productListDatalist = document.getElementById('productListDatalist'); // Populated by PHP initially
    const quantityInput = document.getElementById('quantityInput');
    const addToCartForm = document.getElementById('addToCartForm');
    const cartTableBody = document.getElementById('cartTable')?.querySelector('tbody');
    const emptyCartRow = document.getElementById('emptyCartRow');
    const grandTotalElement = document.getElementById('grandTotal');
    const generateBillBtn = document.getElementById('generateBillBtn');
    
    const activateScannerModeBtn = document.getElementById('activateScannerModeBtn');
    const deactivateScannerModeBtn = document.getElementById('deactivateScannerModeBtn');
    const desktopScannerStatus = document.getElementById('desktopScannerStatus');
    
    // CSRF Token (assuming it's in a hidden input rendered by PHP in pos.php template)
    const csrfTokenInput = document.querySelector('input[name="pos_action_csrf"]');
    const csrfToken = csrfTokenInput ? csrfTokenInput.value : null;

    // --- State ---
    let cart = [];
    let productsData = []; // To store products fetched or passed from PHP
    let desktopPairingPollInterval = null;
    let isDesktopScannerActive = false;

    // --- Helper Functions ---
    function displayDesktopStatus(message, type = 'info') {
        if (!desktopScannerStatus) return;
        desktopScannerStatus.textContent = message;
        desktopScannerStatus.className = `mt-2 text-sm status-message ${type}`;
    }

    function updateCartDisplay() {
        if (!cartTableBody || !emptyCartRow || !grandTotalElement || !generateBillBtn) return;

        cartTableBody.innerHTML = ''; // Clear existing rows except header
        let grandTotal = 0;

        if (cart.length === 0) {
            cartTableBody.appendChild(emptyCartRow);
            emptyCartRow.style.display = 'table-row';
            generateBillBtn.disabled = true;
        } else {
            emptyCartRow.style.display = 'none';
            generateBillBtn.disabled = false;
            cart.forEach((item, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.product_name}</td>
                    <td>₹${item.price.toFixed(2)}</td>
                    <td>${item.quantity}</td>
                    <td>₹${item.total.toFixed(2)}</td>
                    <td><button class="btn btn-sm btn-danger" onclick="window.handleRemoveFromCart(${index})">Remove</button></td>
                `;
                cartTableBody.appendChild(tr);
                grandTotal += item.total;
            });
        }
        grandTotalElement.textContent = `₹${grandTotal.toFixed(2)}`;
    }

    window.handleRemoveFromCart = function(index) { // Make it global for inline onclick
        const item = cart[index];
        cart.splice(index, 1);
        updateCartDisplay();
        if (window.popupNotification) window.popupNotification.info(`${item.product_name} removed from cart.`);
    };
    
    async function addProductToCartById(productId, quantity = 1, productName = null, productPrice = null) {
        // Find product in local list (productsData should be populated from datalist or initial fetch)
        let product = productsData.find(p => p.id === productId);

        if (!product && productName && productPrice !== null) {
            // Product details came from scan, not in local list (maybe outdated local list)
            product = { id: productId, name: productName, price: parseFloat(productPrice), stock: Infinity }; // Assume stock available, server will verify
             if(window.popupNotification) window.popupNotification.info(`Product '${productName}' added from scan.`, "Product Info");
        } else if (!product) {
            if(window.popupNotification) window.popupNotification.warning(`Product ID ${productId} not found in local list.`, "Cart Error");
            return false;
        }

        // Fetch live stock from server to ensure availability
        try {
            // Note: In a high-traffic POS, fetching product details for every add might be slow.
            // A more robust solution might involve websockets or an optimistic update with server-side reconciliation.
            // For now, this ensures stock accuracy.
            const stockCheckResponse = await fetch(window.BASE_PATH + `/api/products/${productId}`);
            if (!stockCheckResponse.ok) throw new Error('Product not found on server for stock check.');
            const stockCheckResult = await stockCheckResponse.json();
            
            if (!stockCheckResult.success || typeof stockCheckResult.data.stock === 'undefined') {
                if(window.popupNotification) window.popupNotification.error(`Could not verify stock for ${product.name}.`, "Stock Error");
                return false;
            }
            const currentStock = parseInt(stockCheckResult.data.stock);

            const existingItem = cart.find(item => item.product_id === product.id);
            const quantityToAdd = quantity;
            const totalQuantityInCart = (existingItem ? existingItem.quantity : 0) + quantityToAdd;

            if (totalQuantityInCart > currentStock) {
                if(window.popupNotification) window.popupNotification.warning(`Only ${currentStock} of ${product.name} available. Cannot add ${quantityToAdd}.`, "Stock Alert");
                return false;
            }
            
            if (existingItem) {
                existingItem.quantity += quantityToAdd;
                existingItem.total = existingItem.quantity * existingItem.price;
            } else {
                cart.push({
                    product_id: product.id,
                    product_name: product.name, // Use name from productsData (or server if fetched fresh)
                    price: parseFloat(product.price),
                    quantity: quantityToAdd,
                    total: quantityToAdd * parseFloat(product.price)
                });
            }
            updateCartDisplay();
            if (productSearch) productSearch.value = '';
            if (quantityInput) quantityInput.value = '1';
            if (window.popupNotification) window.popupNotification.success(`${product.name} (qty: ${quantityToAdd}) added to cart.`, "Cart Update");
            displayDesktopStatus(`${product.name} added to cart.`, 'success');
            return true;

        } catch (error) {
            console.error("Stock check or add to cart error:", error);
            if(window.popupNotification) window.popupNotification.error("Error adding to cart: " + error.message, "Cart Error");
            return false;
        }
    }


    // --- Event Listeners ---
    if (addToCartForm) {
        addToCartForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const productNameInput = productSearch.value;
            const quantityVal = parseInt(quantityInput.value);

            if (!productNameInput || quantityVal < 1) {
                if (window.popupNotification) window.popupNotification.warning("Please select a product and enter a valid quantity.");
                return;
            }
            const selectedOption = Array.from(productListDatalist.options).find(opt => opt.value === productNameInput);
            if (!selectedOption || !selectedOption.dataset.id) {
                if (window.popupNotification) window.popupNotification.warning("Product not found in the list. Please select a valid product.");
                return;
            }
            addProductToCartById(selectedOption.dataset.id, quantityVal, selectedOption.value, parseFloat(selectedOption.dataset.price));
        });
    }

    if (generateBillBtn) {
        generateBillBtn.addEventListener('click', async () => {
            if (cart.length === 0) {
                if(window.popupNotification) window.popupNotification.warning("Cart is empty."); return;
            }
            try {
                const response = await fetch(window.BASE_PATH + '/api/bills/generate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken && {'X-CSRF-TOKEN': csrfToken})
                    },
                    body: JSON.stringify({ items: cart })
                });
                const result = await response.json();
                if (result.success) {
                    if(window.popupNotification) window.popupNotification.success("Bill generated successfully!", "Bill Generated");
                    if(window.confirmNotification) { // Use confirmNotification for a nicer display
                        window.confirmNotification(
                            `<h3>Bill #${result.bill_id}</h3><p>Amount: ₹${parseFloat(result.total_amount).toFixed(2)}</p><p>Thank you!</p>`,
                            () => { // onConfirm
                                cart = []; updateCartDisplay();
                                if (isDesktopScannerActive) deactivateScannerModeFromServer(true);
                            },
                            () => { // onCancel (optional, could also clear cart)
                                cart = []; updateCartDisplay(); // Also clear cart if they cancel the modal
                                if (isDesktopScannerActive) deactivateScannerModeFromServer(true);
                            },
                            { title: "Bill Generated", confirmText: "New Bill", cancelText:"Close" }
                        );
                    } else { // Fallback if confirmNotification is not available
                        alert(`Bill #${result.bill_id} generated. Amount: ₹${parseFloat(result.total_amount).toFixed(2)}`);
                        cart = []; updateCartDisplay();
                        if (isDesktopScannerActive) deactivateScannerModeFromServer(true);
                    }
                } else {
                    if(window.popupNotification) window.popupNotification.error("Bill generation failed: " + (result.message || "Error"));
                }
            } catch (error) {
                console.error("Error generating bill:", error);
                if(window.popupNotification) window.popupNotification.error("An error occurred while generating the bill.");
            }
        });
    }

    // --- Mobile Scanner Integration ---
    async function activateScannerModeOnServer() {
        if (!activateScannerModeBtn || !deactivateScannerModeBtn) return;
        activateScannerModeBtn.disabled = true;
        displayDesktopStatus("Activating mobile scanner mode...", 'info');
        try {
            const response = await fetch(window.BASE_PATH + '/api/scanner/activate-pos', {
                 method: 'POST',
                 headers: { ...(csrfToken && {'X-CSRF-TOKEN': csrfToken}) }
            });
            const result = await response.json();
            if (result.success) {
                isDesktopScannerActive = true;
                displayDesktopStatus(`Scanner mode activated for ${result.staff_username || 'you'}. Mobile can now connect.`, 'success');
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
            await fetch(window.BASE_PATH + '/api/scanner/deactivate-pos', {
                method: 'POST',
                headers: { ...(csrfToken && {'X-CSRF-TOKEN': csrfToken}) }
            });
        } catch (error) { console.error("Error deactivating scanner on server:", error); }
    }
    if(deactivateScannerModeBtn) deactivateScannerModeBtn.addEventListener('click', () => deactivateScannerModeFromServer(false));

    function startPollingForScannedItemsDesktop() {
        if (desktopPairingPollInterval) clearInterval(desktopPairingPollInterval);
        desktopPairingPollInterval = setInterval(async () => {
            if (!isDesktopScannerActive) {
                clearInterval(desktopPairingPollInterval); desktopPairingPollInterval = null; return;
            }
            try {
                const response = await fetch(window.BASE_PATH + `/api/scanner/items`); // GET
                const result = await response.json();
                if (result.success && result.items && result.items.length > 0) {
                    result.items.forEach(item => {
                        addProductToCartById(item.product_id, item.quantity, item.product_name, item.price);
                    });
                } else if (result.success === false && result.message) {
                     displayDesktopStatus(`Scanner: ${result.message}`, 'info');
                }
            } catch (error) { /* console.warn("Desktop polling error:", error); */ }
        }, 2500);
    }
    
    async function checkInitialDesktopScannerStatus() {
        if (!activateScannerModeBtn || !deactivateScannerModeBtn) return;
        try {
            const response = await fetch(window.BASE_PATH + '/api/scanner/check-pos-activation'); // GET
            const data = await response.json();
            if (data.success && data.is_active) {
                isDesktopScannerActive = true;
                displayDesktopStatus(`Scanner mode is ALREADY ACTIVE for ${data.staff_username || 'you'}. ${data.message}`, 'success');
                activateScannerModeBtn.style.display = 'none';
                deactivateScannerModeBtn.style.display = 'inline-block';
                startPollingForScannedItemsDesktop();
            } else {
                isDesktopScannerActive = false;
                displayDesktopStatus(data.message || "Scanner mode is not active.", 'info');
                activateScannerModeBtn.style.display = 'inline-block';
                deactivateScannerModeBtn.style.display = 'none';
            }
        } catch (error) {
            displayDesktopStatus('Error checking initial scanner status.', 'error');
        }
    }

    // --- Initialization ---
    function initializePOS() {
        // Populate productsData from the datalist options (which were rendered by PHP)
        if (productListDatalist) {
            productsData = Array.from(productListDatalist.options).map(opt => ({
                id: opt.dataset.id,
                name: opt.value,
                price: parseFloat(opt.dataset.price),
                stock: parseInt(opt.dataset.stock) // Or fetch live stock if preferred
            }));
        }
        updateCartDisplay();
        checkInitialDesktopScannerStatus();
    }

    initializePOS();
});
