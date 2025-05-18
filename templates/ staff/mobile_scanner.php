<?php
// This template expects to be rendered within a layout, e.g., layouts/minimal.php
// The layout will provide <html>, <head>, <body>, global.css, and popup-notification.js.
// Specific scripts for this page (html5-qrcode.min.js, mobile-scanner.js)
// should be passed via $pageScripts array to the layout by the controller.

// Example Controller render call:
// $this->render('staff/mobile_scanner.php', [
//     'pageTitle' => 'Mobile Barcode Scanner',
//     'pageScripts' => [
//         'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js', // CDN script
//         '/js/mobile-scanner.js' // Local script
//     ]
// ], 'layouts/minimal.php');

// $pageTitle is available from the layout via the controller
// $session (user session data) is available from the layout via the controller
// $e is available from the layout via the View class
?>
<style>
    /* Styles specific to mobile_scanner, previously in its <head> or original HTML */
    /* Integrate these with global.css or keep specific ones here */
    /* body styles removed as they come from global.css via layout */
    .scanner-container {
        padding: 10px; /* Add some padding if not full bleed */
        /* Adjust max-width for mobile view */
        max-width: 600px; /* Limit width on wider screens */
        margin: 0 auto; /* Center the container */
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
        /* max-width handled by container */
        margin: 10px auto;
        border: 1px solid var(--border-color-subtle);
        background: var(--bg-surface-alt);
        min-height: 200px; /* Give it a min height */
        /* Remove default text inside the region */
        text-align: center; /* For centering potential messages if camera fails */
        line-height: 200px; /* Vertically center messages */
        color: var(--text-secondary);
    }
     #scanner-region div { /* Style the div created by html5-qrcode */
        display: block;
        width: 100% !important;
        height: auto !important; /* Allow video aspect ratio */
        min-height: inherit; /* Respect container min-height */
        max-width: 100% !important;
        max-height: calc(100vh - 250px); /* Prevent excessive height on large screens */
     }
     #scanner-region video { /* Style the video element */
         width: 100% !important;
         height: auto !important;
         min-height: inherit;
     }


    #statusMessage {
        margin-top: 10px;
        padding: 10px;
        border-radius: var(--border-radius-sm);
        font-size: 0.9rem; /* Smaller font */
    }
    .status-message.info { background-color: var(--info-bg); color: var(--info-text-emphasis); border: 1px solid var(--info); }
    .status-message.error { background-color: var(--error-bg); color: var(--error-text-emphasis); border: 1px solid var(--error); }
    .status-message.success { background-color: var(--success-bg); color: var(--success-text-emphasis); border: 1px solid var(--success); } /* Added success style */
    .status-message.warning { background-color: var(--warning-bg); color: var(--warning-text-emphasis); border: 1px solid var(--warning); } /* Added warning style */


    .product-info {
        margin-top: 10px;
        padding: 10px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color-subtle);
        border-radius: var(--border-radius-sm);
        font-size: 0.9rem; /* Smaller font */
    }
    .controls {
        margin-top: 10px;
        text-align: center;
        display: flex; /* Make controls a flex container */
        justify-content: center; /* Center buttons */
        gap: 10px; /* Spacing between buttons */
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

    @media (max-width: 480px) {
        .scanner-container {
            padding: 5px;
        }
        .scanner-page-header h1 {
            font-size: 1.2rem !important;
        }
        .user-info-scanner {
            font-size: 0.7rem;
        }
         #scanner-region {
             min-height: 150px; /* Smaller min height on small screen */
         }
         #scanner-region div, #scanner-region video {
             max-height: calc(100vh - 200px); /* Adjust max height for small screens */
         }
         .controls {
             flex-direction: column; /* Stack buttons */
             gap: 5px;
         }
         .controls .btn {
             width: 100%; /* Full width buttons when stacked */
         }
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
         <!-- Add a 'Scan Manually' button later if needed -->
    </div>

    <div id="recentScans" class="glass p-2 mt-2" style="display:none;">
        <h4 style="font-size: 0.9rem; margin-bottom: 5px;">Recent Scans:</h4>
        <ul id="recentScansList" style="list-style: none; padding: 0;"></ul>
    </div>
    <a href="/logout" class="btn" style="background-color: var(--error); margin-top: 20px; font-size: 0.9rem; text-align: center;">Logout</a>
</div>

<?php
// The actual <script src="html5-qrcode.min.js"></script> (CDN) and <script src="/js/mobile-scanner.js"></script> (local)
// must be loaded by the layout (e.g., minimal.php) via the $pageScripts array.
// Ensure the MobileScannerController passes these to the render method.
?>