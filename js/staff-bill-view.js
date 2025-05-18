// public/js/staff-bill-view.js
document.addEventListener('DOMContentLoaded', function() {
    const billSearchInput = document.getElementById('billSearch');
    const billListContainer = document.getElementById('billList');
    const billDetailsModal = document.getElementById('billDetailsModal');
    const billDetailsContent = document.getElementById('billDetailsContent');
    
    let allBillsData = []; // To store all bills fetched initially or passed from PHP
    let productsLookupData = {}; // To store product ID -> name mapping

    // Function to initialize data (either from global JS vars set by PHP or by fetching)
    async function initializeBillData() {
        // Option 1: Data passed via a global JS variable from PHP (more complex to set up now)
        // if (typeof initialBillsData !== 'undefined' && typeof initialProductsLookup !== 'undefined') {
        //     allBillsData = initialBillsData;
        //     productsLookupData = initialProductsLookup;
        //     renderBills();
        //     return;
        // }

        // Option 2: Fetch data on load (simpler for this refactor)
        try {
            const [billsResponse, productsResponse] = await Promise.all([
                fetch('/api/bills'),      // Assuming your BillController::apiGetBills exists
                fetch('/api/products')  // Assuming your ProductController::apiGetProducts exists
            ]);

            if (!billsResponse.ok || !productsResponse.ok) {
                throw new Error('Failed to load initial bill data.');
            }

            const billsResult = await billsResponse.json();
            const productsResult = await productsResponse.json();

            if (billsResult.success && Array.isArray(billsResult.data)) {
                allBillsData = billsResult.data;
            }
            if (productsResult.success && Array.isArray(productsResult.data)) {
                productsResult.data.forEach(p => {
                    productsLookupData[p._id.$oid || p._id] = p.name;
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
    
    function formatDateFromMongo(mongoDate) {
        if (!mongoDate) return 'N/A';
        try {
            // Handle both { $date: { $numberLong: "..." } } and { $date: "YYYY-MM-DDTHH:mm:ss.sssZ" }
            const timestamp = mongoDate.$numberLong ? parseInt(mongoDate.$numberLong) : new Date(mongoDate).getTime();
            return new Date(timestamp).toLocaleDateString();
        } catch (e) {
            return 'Invalid Date';
        }
    }
    function formatTimeFromMongo(mongoDate) {
        if (!mongoDate) return 'N/A';
         try {
            const timestamp = mongoDate.$numberLong ? parseInt(mongoDate.$numberLong) : new Date(mongoDate).getTime();
            return new Date(timestamp).toLocaleTimeString();
        } catch (e) {
            return 'Invalid Time';
        }
    }


    function renderBills(searchTerm = '') {
        if (!billListContainer) return;
        if (allBillsData.length === 0) {
            billListContainer.innerHTML = '<p class="text-center text-light">No bills found.</p>';
            return;
        }

        const lowerSearchTerm = searchTerm.toLowerCase();
        const filteredBills = allBillsData.filter(bill => {
            const billId = bill._id.$oid || bill._id || '';
            if (billId.toLowerCase().includes(lowerSearchTerm)) return true;

            // Search in product names within bill items
            if (bill.items && Array.isArray(bill.items)) {
                for (const item of bill.items) {
                    const productName = item.product_name || getProductName(item.product_id?.$oid || item.product_id);
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
            const billId = bill._id.$oid || bill._id;
            const displayBillId = billId ? billId.substr(-6) : 'N/A';
            const date = formatDateFromMongo(bill.created_at?.$date || bill.created_at);
            const totalAmount = bill.total_amount !== undefined ? parseFloat(bill.total_amount).toFixed(2) : '0.00';

            let summaryText = `Contains ${bill.items?.length || 0} item(s).`;
            if (bill.items && bill.items[0] && bill.items[0].product_name) {
                summaryText = `Product: ${bill.items[0].product_name}${bill.items.length > 1 ? ' & more' : ''}`;
            }


            return `
                <div class="card-base bill-card-item" data-bill-id="${billId}">
                    <h3 class="card-title">Bill #${displayBillId}</h3>
                    <p class="card-meta">Date: ${date}</p>
                    <p>${summaryText}</p>
                    <p>Amount: ₹${totalAmount}</p>
                    <div class="card-actions">
                        <button class="btn btn-sm" onclick="window.viewBillDetails('${billId}')">View Details</button>
                    </div>
                </div>
            `;
        }).join('');
    }

    window.viewBillDetails = function(billId) { // Make it global for onclick
        const bill = allBillsData.find(b => (b._id.$oid || b._id) === billId);
        if (!bill || !billDetailsContent || !billDetailsModal) return;

        let itemsHtml = '<ul style="list-style:none; padding-left:0;">';
        if (bill.items && Array.isArray(bill.items)) {
            bill.items.forEach(item => {
                const productName = item.product_name || getProductName(item.product_id?.$oid || item.product_id);
                itemsHtml += `<li>${item.quantity} x ${productName} @ ₹${parseFloat(item.price_per_unit).toFixed(2)} = ₹${parseFloat(item.item_total).toFixed(2)}</li>`;
            });
        }
        itemsHtml += '</ul>';
        
        const date = formatDateFromMongo(bill.created_at?.$date || bill.created_at);
        const time = formatTimeFromMongo(bill.created_at?.$date || bill.created_at);

        billDetailsContent.innerHTML = `
            <h2>Bill Details #${billId.substr(-6)}</h2>
            <p><b>Full Bill ID:</b> ${billId}</p>
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
    };

    window.closeModal = function() { // Make it global
        if (billDetailsModal) billDetailsModal.style.display = 'none';
    };

    if (billSearchInput) {
        billSearchInput.addEventListener('input', function() {
            renderBills(this.value.trim());
        });
    }

    // Initial Load
    initializeBillData();
});
