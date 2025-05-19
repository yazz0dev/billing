// public/js/staff-bill-view.js
document.addEventListener('DOMContentLoaded', function() {
    const billSearchInput = document.getElementById('billSearch');
    const billListContainer = document.getElementById('billList');
    const billDetailsModal = document.getElementById('billDetailsModal');
    const billDetailsContent = document.getElementById('billDetailsContent');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    let allBillsData = [];
    let productsLookupData = {}; // Product ID -> name

    async function initializeBillData() {
        if(!billListContainer) return; // Element not on page
        try {
            const [billsResponse, productsResponse] = await Promise.all([
                fetch(`${window.APP_URL}/api/bills`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrfToken && {'X-CSRF-TOKEN': csrfToken}) } }),
                fetch(`${window.APP_URL}/api/products`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrfToken && {'X-CSRF-TOKEN': csrfToken}) } })
            ]);

            if (!billsResponse.ok || !productsResponse.ok) {
                throw new Error('Failed to load initial bill or product data.');
            }

            const billsResult = await billsResponse.json();
            const productsResult = await productsResponse.json();

            if (billsResult.success && Array.isArray(billsResult.data)) {
                allBillsData = billsResult.data;
            }
            if (productsResult.success && Array.isArray(productsResult.data)) {
                productsResult.data.forEach(p => {
                    productsLookupData[p.id || p._id] = p.name;
                });
            }
            renderBills();
        } catch (error) {
            console.error("Error initializing bill data:", error);
            if(billListContainer) billListContainer.innerHTML = '<p class="text-danger text-center">Could not load bill history.</p>';
            if(window.popupNotification) window.popupNotification.error('Could not load bill history.', 'Data Error');
        }
    }

    function getProductName(productId) {
        return productsLookupData[productId] || 'Unknown Product';
    }
    
    function formatDate(dateString) { // Laravel dates are usually standard ISO
        if (!dateString) return 'N/A';
        try { return new Date(dateString).toLocaleDateString(); } 
        catch (e) { return 'Invalid Date'; }
    }
    function formatTime(dateString) {
        if (!dateString) return 'N/A';
        try { return new Date(dateString).toLocaleTimeString(); }
        catch (e) { return 'Invalid Time'; }
    }

    function renderBills(searchTerm = '') {
        if (!billListContainer) return;
        if (allBillsData.length === 0 && searchTerm === '') { // Only show "no bills" if no search and no data
            billListContainer.innerHTML = '<p class="text-center text-light">No bills found.</p>';
            return;
        }

        const lowerSearchTerm = searchTerm.toLowerCase();
        const filteredBills = allBillsData.filter(bill => {
            const billId = String(bill.id || bill._id || '');
            if (billId.toLowerCase().includes(lowerSearchTerm)) return true;
            if (bill.username && bill.username.toLowerCase().includes(lowerSearchTerm)) return true;

            if (bill.items && Array.isArray(bill.items)) {
                for (const item of bill.items) {
                    const productName = item.product_name || getProductName(item.product_id);
                    if (productName.toLowerCase().includes(lowerSearchTerm)) return true;
                }
            }
            return false;
        });

        if (filteredBills.length === 0) {
            billListContainer.innerHTML = `<p class="text-center text-light">No bills matching "${searchTerm}".</p>`;
            return;
        }

        billListContainer.innerHTML = filteredBills.map(bill => {
            const billId = String(bill.id || bill._id);
            const displayBillId = billId ? billId.substr(-6) : 'N/A';
            const date = formatDate(bill.created_at);
            const totalAmount = bill.total_amount !== undefined ? parseFloat(bill.total_amount).toFixed(2) : '0.00';
            let summaryText = `Contains ${bill.items?.length || 0} item(s).`;
            if (bill.items && bill.items[0] && (bill.items[0].product_name || getProductName(bill.items[0].product_id))) {
                summaryText = `Product: ${bill.items[0].product_name || getProductName(bill.items[0].product_id)}${bill.items.length > 1 ? ' & more' : ''}`;
            }

            return `
                <div class="card-base bill-card-item" data-bill-id="${billId}">
                    <h3 class="card-title">Bill #${displayBillId}</h3>
                    <p class="card-meta">Date: ${date}</p>
                    <p class="card-meta">Billed by: ${bill.username || 'N/A'}</p>
                    <p>${summaryText}</p>
                    <p>Amount: ₹${totalAmount}</p>
                    <div class="card-actions">
                        <button class="btn btn-sm" onclick="window.viewBillDetails('${billId}')">View Details</button>
                    </div>
                </div>
            `;
        }).join('');
    }

    window.viewBillDetails = async function(billId) {
        if (!billDetailsContent || !billDetailsModal) return;
        
        // Fetch single bill for details to ensure up-to-date info and reduce initial load
        try {
            const response = await fetch(`${window.APP_URL}/api/bills/${billId}`, {
                 headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrfToken && {'X-CSRF-TOKEN': csrfToken}) }
            });
            if (!response.ok) {
                if (window.popupNotification) window.popupNotification.error("Bill not found or error fetching details.");
                return;
            }
            const result = await response.json();
            const bill = result.data;

            if (!bill) return;

            let itemsHtml = '<ul style="list-style:none; padding-left:0;">';
            if (bill.items && Array.isArray(bill.items)) {
                bill.items.forEach(item => {
                    const productName = item.product_name || getProductName(item.product_id);
                    itemsHtml += `<li>${item.quantity} x ${productName} @ ₹${parseFloat(item.price_per_unit).toFixed(2)} = ₹${parseFloat(item.item_total).toFixed(2)}</li>`;
                });
            }
            itemsHtml += '</ul>';
            
            const date = formatDate(bill.created_at);
            const time = formatTime(bill.created_at);

            billDetailsContent.innerHTML = `
                <h2>Bill Details #${String(bill.id || bill._id).substr(-6)}</h2>
                <p><b>Full Bill ID:</b> ${bill.id || bill._id}</p>
                <p><b>Date:</b> ${date}</p>
                <p><b>Time:</b> ${time}</p>
                <p><b>Billed by:</b> ${bill.username || 'N/A'}</p>
                <h4 style="margin-top:1rem; margin-bottom:0.5rem;">Items:</h4>
                ${itemsHtml}
                <hr style="margin:1rem 0;">
                <p style="font-size:1.1em;"><b>Total Amount:</b> ₹${parseFloat(bill.total_amount).toFixed(2)}</p>
                <p><b>Status:</b> <span style="color:var(--success);">Completed</span></p>
            `;
            billDetailsModal.style.display = 'flex';
        } catch (error) {
            console.error("Error fetching bill details:", error);
            if (window.popupNotification) window.popupNotification.error("Could not load bill details.");
        }
    };

    window.closeModal = function() {
        if (billDetailsModal) billDetailsModal.style.display = 'none';
    };

    if (billSearchInput) {
        billSearchInput.addEventListener('input', function() {
            renderBills(this.value.trim());
        });
    }
    initializeBillData();
});