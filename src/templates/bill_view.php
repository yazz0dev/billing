<?php // templates/staff/bill_view.php
// $pageTitle, $bills, $productsLookup (array mapping product_id to name) are available
?>

<h1 class="page-title"><?php echo $e($pageTitle); ?></h1>
<section class="content-section glass">
    <h2 class="section-title">Search Bills</h2>
    <input type="text" id="billSearch" class="bill-search-input" placeholder="Search by Product Name or Bill ID...">
    <div id="billList" class="card-list mt-3">
        <?php if (empty($bills)): ?>
            <p class="text-center text-light">No bills found.</p>
        <?php else: ?>
            <?php foreach ($bills as $bill):
                // Ensure bill structure is consistent. $bill is now an array.
                $billId = isset($bill['_id']['$oid']) ? $bill['_id']['$oid'] : (string)($bill['_id'] ?? 'N/A');
                $billDate = isset($bill['created_at']['$date']) ? $bill['created_at']['$date'] : ($bill['created_at'] ?? null);
                $formattedDate = $billDate ? (new DateTime($billDate['$numberLong'] ? '@'.($billDate['$numberLong']/1000) : $billDate))->format('Y-m-d') : 'N/A';
                $totalAmount = $bill['total_amount'] ?? 0;
            ?>
            <div class="card-base bill-card-item" data-bill-id="<?php echo $e($billId); ?>">
                <h3 class="card-title">Bill #<?php echo $e(substr($billId, -6)); // Display last 6 chars for brevity ?></h3>
                <p class="card-meta">Date: <?php echo $e($formattedDate); ?></p>
                <?php
                // Display first product for summary, or count of items
                $summaryText = "Contains " . count($bill['items'] ?? []) . " item(s).";
                if (!empty($bill['items'][0]['product_name'])) {
                    $summaryText = "Product: " . $e($bill['items'][0]['product_name']) . (count($bill['items']) > 1 ? " & more" : "");
                }
                ?>
                <p><?php echo $summaryText; ?></p>
                <p>Amount: ₹<?php echo $e(number_format($totalAmount, 2)); ?></p>
                <div class="card-actions">
                    <button class="btn btn-sm" onclick="viewBillDetails('<?php echo $e($billId); ?>')">View Details</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Bill Details Modal -->
<div id="billDetailsModal" class="bill-details-modal-backdrop" style="display:none;">
    <div class="bill-details-modal-content glass">
        <button class="bill-details-modal-close-btn" onclick="closeModal()">✕</button>
        <div id="billDetailsContent">
            <!-- Bill details will be rendered here by JS -->
        </div>
    </div>
</div>

<?php $pageScripts = ['/js/staff-bill-view.js']; ?>

<script>
// This will now be in public/js/staff-bill-view.js
// PHP provides initial $bills and $productsLookup data to the JS via global variables or data attributes.
// Or, the JS can fetch /api/bills on load.
</script>
