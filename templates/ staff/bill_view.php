<?php // templates/staff/bill_view.php
// $pageTitle, $bills, $productsLookup (array mapping product_id to name) are available
// $e is available
// $session is available from the layout
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
                $billDateObj = $bill['created_at'] ?? null; // Should be MongoDB\BSON\UTCDateTime
                $formattedDate = 'N/A';
                if ($billDateObj instanceof MongoDB\BSON\UTCDateTime) {
                     try {
                        $formattedDate = $billDateObj->toDateTime()->format('Y-m-d H:i:s'); // Format as needed
                     } catch (\Exception $e) {
                        // Date conversion failed
                        $formattedDate = 'Invalid Date';
                     }
                } elseif (is_string($billDateObj)) {
                     // Fallback if it's somehow a string date (less common for MongoDB)
                     try {
                        $formattedDate = (new DateTime($billDateObj))->format('Y-m-d H:i:s');
                     } catch (\Exception $e) {
                        $formattedDate = 'Invalid Date';
                     }
                } else {
                    // Handle other potential formats or missing date
                    $formattedDate = 'N/A';
                }


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

<?php
// Pass initial data to JS if needed (alternative to JS fetching)
// The JS will fetch /api/bills anyway, so no need to pass data via PHP variables here.
// Just include the necessary JS file.

$pageScripts = [
    '/js/staff-bill-view.js',
];
?>