<?php
$pageTitle = "Bill History";
$bodyClass = "";

// Additional styles if needed
$additionalStyles = '
<style>
    /* Additional page-specific styles */
</style>
';

// Include header
require_once '../includes/header.php';
?>

<h1 class="page-title">Bill History</h1>
<section class="content-section glass">
    <h2 class="section-title">Search Bills</h2>
    <input type="text" id="billSearch" class="bill-search-input" placeholder="Search by Product Name or Bill ID...">
    <div id="billList" class="card-list mt-3">
        <!-- Bill cards will be rendered here -->
         <p class="text-center text-light">Loading bill history...</p>
    </div>
</section>

<!-- Bill Details Modal -->
<div id="billDetailsModal" class="bill-details-modal-backdrop">
    <div class="bill-details-modal-content glass">
        <button class="bill-details-modal-close-btn" onclick="closeModal()">✕</button>
        <div id="billDetailsContent">
            <!-- Bill details will be rendered here -->
        </div>
    </div>
</div>

<script>
    let bills = [];
    let products = [];

    // Fetch products for name lookup
    async function fetchProducts() {
        const res = await fetch('/billing/server.php?action=getProducts');
        products = await res.json();
    }

    // Fetch bills
    async function fetchBills() {
        const res = await fetch('/billing/server.php?action=getBills');
        bills = await res.json();
        renderBills();
    }

    // Get product name by id
    function getProductName(productId) {
        const product = products.find(p => p.id == productId);
        return product ? product.name : 'Unknown Product';
    }

    // Render bills
    function renderBills(search = '') {
        const billList = document.getElementById('billList');
        
        if (!bills || !bills.length) {
            billList.innerHTML = '<p class="text-center text-light">No bills found.</p>';
            return;
        }
        
        // Filter bills by search term if provided
        const filteredBills = search 
            ? bills.filter(bill => {
                const productName = getProductName(bill.product_id).toLowerCase();
                return productName.includes(search.toLowerCase()) || 
                       bill.id.toString().includes(search);
            })
            : bills;
        
        if (!filteredBills.length) {
            billList.innerHTML = `<p class="text-center text-light">No bills matching "${search}" found.</p>`;
            return;
        }
        
        billList.innerHTML = filteredBills.map(bill => `
            <div class="card-base">
                <h3 class="card-title">Bill #${bill.id}</h3>
                <p class="card-meta">Date: ${new Date(bill.date).toLocaleDateString()}</p>
                <p>Product: ${getProductName(bill.product_id)}</p>
                <p>Quantity: ${bill.quantity}</p>
                <p>Amount: $${bill.amount.toFixed(2)}</p>
                <div class="card-actions">
                    <button class="btn" onclick="showBillDetails(${bill.id})">View Details</button>
                </div>
            </div>
        `).join('');
    }

    // Show bill details in modal
    function showBillDetails(billId) {
        const bill = bills.find(b => b.id == billId);
        if (!bill) return;
        
        const modal = document.getElementById('billDetailsModal');
        const content = document.getElementById('billDetailsContent');
        
        content.innerHTML = `
            <h2>Bill Details #${bill.id}</h2>
            <p><b>Date:</b> ${new Date(bill.date).toLocaleDateString()}</p>
            <p><b>Time:</b> ${new Date(bill.date).toLocaleTimeString()}</p>
            <p><b>Product:</b> ${getProductName(bill.product_id)}</p>
            <p><b>Quantity:</b> ${bill.quantity}</p>
            <p><b>Amount:</b> $${bill.amount.toFixed(2)}</p>
            <p><b>Status:</b> <span style="color:var(--success);">Completed</span></p>
        `;
        
        modal.style.display = 'flex';
    }

    // Close the modal
    function closeModal() {
        document.getElementById('billDetailsModal').style.display = 'none';
    }

    // Search functionality
    document.getElementById('billSearch').addEventListener('input', function() {
        renderBills(this.value.trim());
    });

    // On page load
    fetchProducts().then(fetchBills);
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?>
