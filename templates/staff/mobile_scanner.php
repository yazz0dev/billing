<?php
// This template expects to be rendered within a layout, e.g., layouts/minimal.php
// The layout will provide <html>, <head>, <body>, global.css, and popup-notification.js.
// Specific scripts for this page (html5-qrcode.min.js, mobile-scanner.js)
// should be passed via $pageScripts array to the layout by the controller.

// Example: Controller should do something like:
// $this->render('staff/mobile_scanner.php', [
//     'pageTitle' => 'Mobile Barcode Scanner',
//     'pageScripts' => [
//         'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
//         '/js/mobile-scanner.js'
//     ]
// ], 'layouts/minimal.php');
?>
<style>
    /* Styles specific to mobile_scanner, formerly in its <head> */
    /* Consider moving these to global.css or a dedicated scanner.css if they grow. */
    body { /* Styles from original staff/index.html are fine, or integrate more with global.css */
        /* background-color: var(--bg-body); color: var(--text-primary); */ /* These will come from global.css via layout */
    }
    .scanner-container {
        padding: 10px; /* Add some padding if not full bleed */
    }
    .scanner-page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding: 0.5rem;
        background-color: var(--bg-surface-alt);
        border-radius: var(--border-radius-sm);
    }
    .user-info-scanner {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    #scanner-region {
        width: 100%;
        max-width: 500px; /* Or as needed */
        margin: 10px auto;
        border: 1px solid var(--border-color-subtle);
        background: var(--bg-surface-alt);
    }
    #statusMessage {
        margin-top: 10px;
        padding: 10px;
        border-radius: var(--border-radius-sm);
    }
    .status-message.info { background-color: var(--info-bg); color: var(--info-text-emphasis); border: 1px solid var(--info); }
    .status-message.error { background-color: var(--error-bg); color: var(--error-text-emphasis); border: 1px solid var(--error); }
    .product-info {
        margin-top: 10px;
        padding: 10px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color-subtle);
        border-radius: var(--border-radius-sm);
    }
    .controls {
        margin-top: 10px;
        text-align: center;
    }
    #recentScans {
        max-height: 150px;
        overflow-y: auto;
    }
    #recentScansList li {
        padding: 5px;
        border-bottom: 1px solid var(--border-color-subtle);
        font-size: 0.85rem;
    }
    #recentScansList li:last-child {
        border-bottom: none;
    }

</style>

<div class="scanner-container">
    <div class="scanner-page-header">
        <h1 class="page-title" style="font-size: 1.4rem; margin-bottom: 0; text-align: left; background: none; color: var(--text-heading); transform: none; left: auto; padding-bottom: 0;">Mobile Scanner</h1>
        <span class="user-info-scanner">User: <strong id="mobileUsernameDisplay"><?php echo $e($_SESSION['username'] ?? 'N/A'); ?></strong></span>
    </div>
    
    <div id="activationInfo" class="glass p-3">
        <p id="activationStatusMessage">Checking activation status with POS terminal...</p>
        <button id="tryActivateScannerBtn" class="btn mt-2" style="display:none;">Try Activating Scanner</button>
    </div>

    <div id="scanner-region" style="display:none;"></div>
    <div id="statusMessage" class="status-message info" style="display:none;"></div>
    <div id="lastScannedProduct" class="product-info" style="display:none;"></div>

    <div class="controls" style="display:none;" id="scanControls">
        <button id="stopScannerBtn" class="btn">Stop Camera</button>
    </div>

    <div id="recentScans" class="glass p-2 mt-2" style="display:none;">
        <h4 style="font-size: 0.9rem; margin-bottom: 5px;">Recent Scans:</h4>
        <ul id="recentScansList" style="list-style: none; padding: 0;"></ul>
    </div>
    <a href="/logout" class="btn" style="background-color: var(--error); margin-top: 20px; font-size: 0.9rem;">Logout</a>
</div>

<?php
// Note: The actual <script src="html5-qrcode.min.js"></script> and <script src="/js/mobile-scanner.js"></script>
// should be loaded by the layout (e.g., minimal.php) via the $pageScripts array.
// Ensure the controller passes these to the render method.
?>
