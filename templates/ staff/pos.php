<?php // templates/staff/pos.php
// $pageTitle, $products (array of product arrays), $csrf_token_name, $csrf_token_value are available
// $session (user session data) is available
?>
<h1 class="page-title"><?php echo $e($pageTitle); ?></h1>

<section class="content-section glass">
    <h2 class="section-title">Mobile Scanner Link</h2>
    <p>User: <strong><?php echo $e($session['username'] ?? 'N/A'); ?></strong></p>
    <div id="pairingUiDesktop" class="flex flex-col md:flex-row gap-2 items-start">
        <div class="flex-grow">
            <button id="activateScannerModeBtn" class="btn">Activate My Mobile Scanner</button>
            <button id="deactivateScannerModeBtn" class="btn" style="display:none; background-color:var(--warning);">Deactivate Mobile Scanner</button>
            <div id="pairingInstructionsDesktop" class="mt-2">
                <p class="text-sm text-secondary">
                    Ensure you are logged in on your mobile device with the same account (<b><?php echo $e($session['username'] ?? 'N/A'); ?></b>).
                    Then, on your mobile, navigate to the "Mobile Scanner" page.
                </p>
            </div>
        </div>
    </div>
    <div id="desktopScannerStatus" class="mt-2 text-sm status-message info">Status: Idle. Click "Activate" to enable.</div>
</section>

<section class="content-section glass mt-4">
    <h2 class="section-title">Add Products to Cart</h2>
    <form id="addToCartForm" autocomplete="off">
         <input type="hidden" name="<?php echo $e($csrf_token_name); ?>" value="<?php echo $e($csrf_token_value); ?>">
        <div class="flex flex-col gap-2 md:flex-row"> 
            <div class="form-group flex-grow mb-0"> 
                <input type="text" id="productSearch" placeholder="Search product or scan..." list="productListDatalist" class="w-full">
                <datalist id="productListDatalist">
                    <?php foreach ($products as $product): ?>
                        <?php
                            // Ensure product ID is correctly extracted, accounting for BSONObjectId structure
                            $productId = isset($product['_id']['$oid']) ? $product['_id']['$oid'] : (string)($product['_id'] ?? '');
                        ?>
                        <option value="<?php echo $e($product['name']); ?>" 
                                data-id="<?php echo $e($productId); ?>"
                                data-price="<?php echo $e($product['price']); ?>"
                                data-stock="<?php echo $e($product['stock']); ?>">
                        </option>
                    <?php endforeach; ?>
                </datalist>
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

<?php $pageScripts = ['/js/staff-pos.js']; ?>
