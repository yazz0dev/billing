// public/js/mobile-scanner.js
document.addEventListener('DOMContentLoaded', function () {
    const stopScannerBtn = document.getElementById('stopScannerBtn');
    const scannerRegion = document.getElementById('scanner-region');
    const statusMessageEl = document.getElementById('statusMessage'); // Renamed for clarity
    const lastScannedProductDiv = document.getElementById('lastScannedProduct');
    const scanControls = document.getElementById('scanControls');
    const recentScansDiv = document.getElementById('recentScans');
    const recentScansList = document.getElementById('recentScansList');
    const activationStatusMessageEl = document.getElementById('activationStatusMessage'); // Renamed
    const tryActivateScannerBtn = document.getElementById('tryActivateScannerBtn');
    const mobileUsernameDisplay = document.getElementById('mobileUsernameDisplay');

    let html5QrCodeScan = null;
    let isScannerHardwareActive = false; // Camera hardware is on/off
    let isSessionActiveForScanning = false; // Server confirmed this mobile can send scans to an active POS session
    let scanCooldown = false;
    let activationCheckInterval = null;
    const COOLDOWN_DURATION = 1500; // ms
    const ACTIVATION_POLL_INTERVAL = 7000; // ms

    function displayScanStatus(message, type = 'info') {
        if (!statusMessageEl) return;
        statusMessageEl.textContent = message;
        statusMessageEl.className = `status-message ${type}`;
        statusMessageEl.style.display = 'block';
    }

    function addRecentScan(productName, code) {
        if (!recentScansDiv || !recentScansList) return;
        recentScansDiv.style.display = 'block';
        const listItem = document.createElement('li');
        listItem.textContent = `${new Date().toLocaleTimeString()}: ${productName || 'N/A'} (${code})`;
        recentScansList.prepend(listItem); // Add to the top
        // Keep only the last 5 scans
        while (recentScansList.children.length > 5) {
            recentScansList.removeChild(recentScansList.lastChild);
        }
    }

    async function attemptToActivateScannerSession() {
        if (!activationStatusMessageEl || !tryActivateScannerBtn) return;

        activationStatusMessageEl.textContent = 'Attempting to activate with POS...';
        tryActivateScannerBtn.style.display = 'none';

        try {
            const response = await fetch('/api/scanner/activate-mobile', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    // No CSRF token needed here typically, relies on session auth established at login
                }
            });
            if (!response.ok) { // Check for non-2xx responses
                let errorMsg = `Activation check failed: ${response.status}`;
                try {
                    const errData = await response.json();
                    errorMsg = errData.message || errorMsg;
                } catch (e) { /* Ignore if response not JSON */ }
                throw new Error(errorMsg);
            }
            const data = await response.json();

            if (data.success && data.session_activated) {
                isSessionActiveForScanning = true;
                activationStatusMessageEl.textContent = `Scanner activated by ${data.staff_username || 'POS'}. Ready to scan.`;
                activationStatusMessageEl.classList.remove('error', 'warning');
                activationStatusMessageEl.classList.add('success'); // Assuming you have CSS for this
                activationStatusMessageEl.parentElement.style.borderColor = 'var(--success)';
                tryActivateScannerBtn.style.display = 'none';

                if (!isScannerHardwareActive) {
                    startBarcodeScannerHardware();
                }
                if (activationCheckInterval) {
                    clearInterval(activationCheckInterval);
                    activationCheckInterval = null;
                }
                if (mobileUsernameDisplay) mobileUsernameDisplay.textContent = data.staff_username || 'Staff';

            } else {
                isSessionActiveForScanning = false;
                activationStatusMessageEl.textContent = data.message || 'POS terminal has not activated scanner mode for your account. Ask staff at POS to click "Activate Mobile Scanner".';
                activationStatusMessageEl.classList.remove('success', 'warning');
                activationStatusMessageEl.classList.add('error'); // Assuming you have CSS for this
                activationStatusMessageEl.parentElement.style.borderColor = 'var(--error)';
                tryActivateScannerBtn.style.display = 'block';

                if (isScannerHardwareActive) {
                    stopBarcodeScannerHardware();
                }
                if (mobileUsernameDisplay) mobileUsernameDisplay.textContent = data.current_user || 'Staff (Not Paired)';
                 // Re-enable polling if not activated but user is still on the page
                if (!activationCheckInterval) {
                    activationCheckInterval = setInterval(attemptToActivateScannerSession, ACTIVATION_POLL_INTERVAL);
                }
            }
        } catch (error) {
            isSessionActiveForScanning = false;
            activationStatusMessageEl.textContent = `Network error or server issue: ${error.message}. Retrying...`;
            activationStatusMessageEl.classList.remove('success', 'warning');
            activationStatusMessageEl.classList.add('error');
            tryActivateScannerBtn.style.display = 'block';
            console.error("Activation check error:", error);
            if (!activationCheckInterval) {
                 activationCheckInterval = setInterval(attemptToActivateScannerSession, ACTIVATION_POLL_INTERVAL);
            }
        }
    }

    function startBarcodeScannerHardware() {
        if (isScannerHardwareActive || !scannerRegion || !scanControls) return;

        displayScanStatus('Initializing camera...', 'info');
        scannerRegion.style.display = 'block';
        scanControls.style.display = 'flex';

        html5QrCodeScan = new Html5Qrcode("scanner-region");
        const config = { fps: 10, qrbox: { width: 280, height: 140 }, aspectRatio: 1.777 }; // Adjusted for typical phone camera

        html5QrCodeScan.start({ facingMode: "environment" }, config, onScanSuccess, onScanFailure)
            .then(() => {
                isScannerHardwareActive = true;
                displayScanStatus(`Camera active. Point at product barcode.`, 'success');
                requestWakeLock();
            })
            .catch(err => {
                displayScanStatus(`Error starting camera: ${err}. Check permissions.`, 'error');
                console.error("Scanner start error:", err);
                scannerRegion.style.display = 'none';
                scanControls.style.display = 'none';
                isScannerHardwareActive = false; // Ensure state is correct
            });
    }

    function stopBarcodeScannerHardware() {
        if (html5QrCodeScan && isScannerHardwareActive) {
            html5QrCodeScan.stop()
                .then(() => {
                    isScannerHardwareActive = false;
                    displayScanStatus('Camera stopped.', 'info');
                    if(html5QrCodeScan) html5QrCodeScan.clear(); // Important to clear resources
                    releaseWakeLock();
                })
                .catch(err => {
                    displayScanStatus(`Error stopping camera: ${err}`, 'error');
                    console.error("Scanner stop error:", err);
                })
                .finally(() => { // Ensure UI updates regardless of stop success/failure
                    if (scannerRegion) scannerRegion.style.display = 'none';
                    if (scanControls) scanControls.style.display = 'none';
                    isScannerHardwareActive = false;
                });
        } else {
            // If already stopped or not initialized, just ensure UI is correct
            if (scannerRegion) scannerRegion.style.display = 'none';
            if (scanControls) scanControls.style.display = 'none';
            isScannerHardwareActive = false;
            releaseWakeLock();
        }
    }

    async function onScanSuccess(decodedText, decodedResult) {
        if (scanCooldown || !isScannerHardwareActive || !isSessionActiveForScanning) return;

        scanCooldown = true;
        setTimeout(() => { scanCooldown = false; }, COOLDOWN_DURATION);

        displayScanStatus(`Scanned: ${decodedText}. Sending...`, 'info');
        if (lastScannedProductDiv) lastScannedProductDiv.textContent = `Last: ${decodedText}`;

        const formData = new FormData();
        formData.append('scanned_product_id', decodedText);
        // formData.append('quantity', 1); // If you want to send quantity from mobile

        try {
            const response = await fetch('/api/scanner/submit-scan', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            if (!response.ok) {
                let errorMsg = `Server error: ${response.status}`;
                try {
                    const errData = await response.json();
                    errorMsg = errData.message || errorMsg;
                } catch(e) { /* ignore */ }
                throw new Error(errorMsg);
            }

            const data = await response.json();

            if (data.success) {
                displayScanStatus(`Sent: ${data.product_name || decodedText}`, 'success');
                addRecentScan(data.product_name, decodedText);
            } else {
                displayScanStatus(`Server: ${data.message || 'Failed to process scan.'}`, 'error');
                addRecentScan(`Failed: ${data.message || 'Error'}`, decodedText);
                // If pairing lost, trigger re-activation check
                if (data.message && data.message.toLowerCase().includes("no active pairing")) {
                    isSessionActiveForScanning = false;
                    if (activationStatusMessageEl) activationStatusMessageEl.textContent = 'Pairing lost or POS deactivated. Re-activate on POS.';
                    if (tryActivateScannerBtn) tryActivateScannerBtn.style.display = 'block';
                    if (isScannerHardwareActive) stopBarcodeScannerHardware();
                    if (!activationCheckInterval) { // Restart polling
                        activationCheckInterval = setInterval(attemptToActivateScannerSession, ACTIVATION_POLL_INTERVAL);
                    }
                }
            }
        } catch (error) {
            displayScanStatus(`Network Error: ${error.message}. Check connection.`, 'error');
            addRecentScan(`Network Error`, decodedText);
            console.error("Scan submission error:", error);
        }
    }

    function onScanFailure(error) {
        // This is called frequently by the library, usually not an actual error unless camera fails.
        // console.warn("QR Scan Failure (often informational):", error);
    }

    // Wake Lock to keep screen on
    let wakeLock = null;
    const requestWakeLock = async () => {
        if ('wakeLock' in navigator) {
            try {
                wakeLock = await navigator.wakeLock.request('screen');
                wakeLock.addEventListener('release', () => {
                    // console.log('Screen Wake Lock released:', wakeLock.released);
                    // If released unexpectedly, try to reacquire if scanner is still active
                    if (isScannerHardwareActive && document.visibilityState === 'visible') {
                       // requestWakeLock(); // Be cautious with immediate re-request
                    }
                });
                // console.log('Screen Wake Lock acquired');
            } catch (err) {
                console.error(`Wake Lock error: ${err.name}, ${err.message}`);
            }
        }
    };

    const releaseWakeLock = () => {
        if (wakeLock !== null && !wakeLock.released) {
            wakeLock.release().then(() => {
                // console.log('Screen Wake Lock manually released');
                wakeLock = null;
            }).catch(err => console.error("Error releasing wake lock:", err));
        }
    };

    document.addEventListener('visibilitychange', async () => {
        if (wakeLock !== null && document.visibilityState === 'visible' && isScannerHardwareActive) {
            await requestWakeLock(); // Try to re-acquire if tab becomes visible and lock was released
        } else if (document.visibilityState !== 'visible' && wakeLock !== null && !wakeLock.released) {
           // Optional: releaseWakeLock(); // Some browsers auto-release on visibility change
        }
    });
    
    // --- Initialization ---
    if (tryActivateScannerBtn) {
        tryActivateScannerBtn.addEventListener('click', attemptToActivateScannerSession);
    }
    if (stopScannerBtn) {
        stopScannerBtn.addEventListener('click', stopBarcodeScannerHardware);
    }

    // Initial attempt to activate and start polling
    attemptToActivateScannerSession();
    if (!isSessionActiveForScanning && !activationCheckInterval) { // Ensure polling starts if not immediately active
        activationCheckInterval = setInterval(attemptToActivateScannerSession, ACTIVATION_POLL_INTERVAL);
    }

    // Graceful cleanup on page unload (though Vercel might terminate functions quickly)
    window.addEventListener('beforeunload', () => {
        if (isScannerHardwareActive) {
            stopBarcodeScannerHardware(); // Attempts to stop camera
        }
        if (activationCheckInterval) {
            clearInterval(activationCheckInterval);
        }
        releaseWakeLock();
    });
});