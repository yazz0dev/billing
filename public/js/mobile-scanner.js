// This is public/js/mobile-scanner.js (contents adapted from original staff/index.html)
document.addEventListener('DOMContentLoaded', function () {
    const stopScannerBtn = document.getElementById('stopScannerBtn');
    const scannerRegion = document.getElementById('scanner-region');
    const statusMessage = document.getElementById('statusMessage');
    const lastScannedProductDiv = document.getElementById('lastScannedProduct');
    const scanControls = document.getElementById('scanControls');
    const recentScansDiv = document.getElementById('recentScans');
    const recentScansList = document.getElementById('recentScansList');
    const activationStatusMessage = document.getElementById('activationStatusMessage');
    const tryActivateScannerBtn = document.getElementById('tryActivateScannerBtn');
    const mobileUsernameDisplay = document.getElementById('mobileUsernameDisplay');

    let html5QrCodeScan = null;
    let isScannerHardwareActive = false;
    let isSessionActiveForScanning = false;
    let scanCooldown = false;
    let activationCheckInterval = null;

    function displayScanStatus(message, type = 'info') {
        if (!statusMessage) return;
        statusMessage.textContent = message;
        statusMessage.className = `status-message ${type}`;
        statusMessage.style.display = 'block';
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
        activationStatusMessage.textContent = 'Attempting to activate with POS...';
        tryActivateScannerBtn.style.display = 'none';
        try {
            // UPDATED API ENDPOINT
            const response = await fetch('/api/scanner/activate-mobile', { method: 'POST' });
            const data = await response.json();

            if (data.success && data.session_activated) {
                isSessionActiveForScanning = true;
                activationStatusMessage.textContent = `Scanner activated by ${data.staff_username || 'POS'}. Ready to scan.`;
                activationStatusMessage.style.color = 'var(--success-text-emphasis)';
                activationStatusMessage.parentElement.style.borderColor = 'var(--success)';
                tryActivateScannerBtn.style.display = 'none';
                if (!isScannerHardwareActive) startBarcodeScannerHardware();
                if (activationCheckInterval) clearInterval(activationCheckInterval);
                activationCheckInterval = null;
                if(mobileUsernameDisplay) mobileUsernameDisplay.textContent = data.staff_username || 'Staff';
            } else {
                isSessionActiveForScanning = false;
                activationStatusMessage.textContent = data.message || 'POS terminal has not activated scanner mode. Ask staff at POS to click "Activate Mobile Scanner".';
                activationStatusMessage.style.color = 'var(--error-text-emphasis)';
                activationStatusMessage.parentElement.style.borderColor = 'var(--error)';
                tryActivateScannerBtn.style.display = 'block';
                if(isScannerHardwareActive) stopBarcodeScannerHardware();
                if(mobileUsernameDisplay) mobileUsernameDisplay.textContent = data.current_user || 'Staff (Not Paired)';
            }
        } catch (error) {
            isSessionActiveForScanning = false;
            activationStatusMessage.textContent = 'Network error checking activation. Will retry.';
            tryActivateScannerBtn.style.display = 'block';
            console.error("Activation check error:", error);
        }
    }
    
    attemptToActivateScannerSession(); // Initial check
    activationCheckInterval = setInterval(attemptToActivateScannerSession, 7000);

    if(tryActivateScannerBtn) tryActivateScannerBtn.addEventListener('click', attemptToActivateScannerSession);
    if(stopScannerBtn) stopScannerBtn.addEventListener('click', stopBarcodeScannerHardware);

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

    function onScanSuccess(decodedText, decodedResult) {
        if (scanCooldown || !isScannerHardwareActive || !isSessionActiveForScanning) return; 
        scanCooldown = true;
        setTimeout(() => { scanCooldown = false; }, 1500);

        displayScanStatus(`Scanned: ${decodedText}. Sending...`, 'info');
        lastScannedProductDiv.textContent = `Last: ${decodedText}`;

        const formData = new FormData();
        // formData.append('action', 'submitScannedProduct'); // Not needed with dedicated API
        formData.append('scanned_product_id', decodedText);

        // UPDATED API ENDPOINT
        fetch('/api/scanner/submit-scan', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayScanStatus(`Sent: ${data.product_name || decodedText}`, 'success');
                    addRecentScan(data.product_name, decodedText);
                } else {
                    displayScanStatus(`Server Error: ${data.message || 'Failed to send.'}`, 'error');
                    addRecentScan(`Failed: ${data.message || 'Error'}`, decodedText);
                     if(data.message && data.message.toLowerCase().includes("no active pairing")){
                        isSessionActiveForScanning = false;
                        activationStatusMessage.textContent = 'Pairing lost or POS deactivated. Re-activate on POS.';
                        tryActivateScannerBtn.style.display = 'block';
                        if(isScannerHardwareActive) stopBarcodeScannerHardware();
                    }
                }
            })
            .catch(error => {
                displayScanStatus(`Network Error: ${error.message}`, 'error');
                addRecentScan(`Network Error`, decodedText);
            });
    }

    function onScanFailure(error) {
        // This is called frequently by the library, usually not an actual error unless camera fails.
        // console.warn("QR Scan Failure (often informational):", error);
    }
    
    // Wake lock logic remains the same
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
});