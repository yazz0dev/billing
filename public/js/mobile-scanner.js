// public/js/mobile-scanner.js
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
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'); // Get from meta tag if available (not typical for minimal layout)

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
        recentScansList.prepend(listItem);
        while (recentScansList.children.length > 5) {
            recentScansList.removeChild(recentScansList.lastChild);
        }
    }

    async function attemptToActivateScannerSession() {
        if (!activationStatusMessage || !tryActivateScannerBtn) return;
        activationStatusMessage.textContent = 'Attempting to activate with POS...';
        tryActivateScannerBtn.style.display = 'none';
        try {
            const response = await fetch(`${window.APP_URL}/api/scanner/activate-mobile`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    ...(csrfToken && {'X-CSRF-TOKEN': csrfToken})
                }
            });
            const data = await response.json();

            if (response.ok && data.success && data.session_activated) {
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
                if(mobileUsernameDisplay && data.current_user) mobileUsernameDisplay.textContent = data.current_user;
            }
        } catch (error) {
            isSessionActiveForScanning = false;
            activationStatusMessage.textContent = 'Network error checking activation. Will retry.';
            tryActivateScannerBtn.style.display = 'block';
            console.error("Activation check error:", error);
        }
    }
    
    if (activationStatusMessage && tryActivateScannerBtn) { // Only run if elements exist
        attemptToActivateScannerSession(); // Initial check
        activationCheckInterval = setInterval(attemptToActivateScannerSession, 7000);
    }


    if(tryActivateScannerBtn) tryActivateScannerBtn.addEventListener('click', attemptToActivateScannerSession);
    if(stopScannerBtn) stopScannerBtn.addEventListener('click', stopBarcodeScannerHardware);

    function startBarcodeScannerHardware() {
        if (isScannerHardwareActive || !scannerRegion || !scanControls || typeof Html5Qrcode === 'undefined') {
             if (typeof Html5Qrcode === 'undefined') console.error("Html5Qrcode library not loaded!");
            return;
        }

        displayScanStatus('Initializing camera...', 'info');
        scannerRegion.style.display = 'block';
        scanControls.style.display = 'flex';

        html5QrCodeScan = new Html5Qrcode("scanner-region");
        const config = { fps: 10, qrbox: { width: 280, height: 140 }, aspectRatio: 1.777 };

        html5QrCodeScan.start({ facingMode: "environment" }, config, onScanSuccess, onScanFailure)
            .then(() => {
                isScannerHardwareActive = true;
                displayScanStatus(`Camera active. Point at product barcode.`, 'success');
                requestWakeLock();
            })
            .catch(err => {
                displayScanStatus(`Error starting camera: ${err}. Check permissions.`, 'error');
                console.error("Scanner start error:", err);
                if(scannerRegion) scannerRegion.style.display = 'none';
                if(scanControls) scanControls.style.display = 'none';
                isScannerHardwareActive = false;
            });
    }

    function stopBarcodeScannerHardware() {
        if (html5QrCodeScan && isScannerHardwareActive) {
            html5QrCodeScan.stop()
                .then(() => {
                    displayScanStatus('Camera stopped.', 'info');
                    if(html5QrCodeScan) html5QrCodeScan.clear();
                    releaseWakeLock();
                })
                .catch(err => {
                    displayScanStatus(`Error stopping camera: ${err}`, 'error');
                    console.error("Scanner stop error:", err);
                })
                .finally(() => {
                    if (scannerRegion) scannerRegion.style.display = 'none';
                    if (scanControls) scanControls.style.display = 'none';
                    isScannerHardwareActive = false;
                });
        } else {
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
        if(lastScannedProductDiv) lastScannedProductDiv.textContent = `Last: ${decodedText}`;

        const payload = { scanned_product_id: decodedText };

        fetch(`${window.APP_URL}/api/scanner/submit-scan`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                ...(csrfToken && {'X-CSRF-TOKEN': csrfToken})
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayScanStatus(`Sent: ${data.product_name || decodedText}`, 'success');
                addRecentScan(data.product_name, decodedText);
            } else {
                displayScanStatus(`Server Error: ${data.message || 'Failed to send.'}`, 'error');
                addRecentScan(`Failed: ${data.message || 'Error'}`, decodedText);
                if(data.code === 403 || (data.message && data.message.toLowerCase().includes("no active pairing"))){
                    isSessionActiveForScanning = false;
                    if(activationStatusMessage) activationStatusMessage.textContent = 'Pairing lost or POS deactivated. Re-activate on POS.';
                    if(tryActivateScannerBtn) tryActivateScannerBtn.style.display = 'block';
                    if(isScannerHardwareActive) stopBarcodeScannerHardware();
                }
            }
        })
        .catch(error => {
            displayScanStatus(`Network Error: ${error.message}`, 'error');
            addRecentScan(`Network Error`, decodedText);
        });
    }

    function onScanFailure(error) { /* console.warn("QR Scan Failure:", error); */ }
    
    let wakeLock = null;
    const requestWakeLock = async () => {
        if ('wakeLock' in navigator) {
            try {
                wakeLock = await navigator.wakeLock.request('screen');
                wakeLock.addEventListener('release', () => {
                    if (isScannerHardwareActive && document.visibilityState === 'visible') { /* requestWakeLock(); */ }
                });
            } catch (err) { console.error(`Wake Lock error: ${err.name}, ${err.message}`); }
        }
    };
    const releaseWakeLock = () => {
        if (wakeLock !== null && !wakeLock.released) {
            wakeLock.release().then(() => { wakeLock = null; }).catch(err => console.error("Error releasing wake lock:", err));
        }
    };
    document.addEventListener('visibilitychange', async () => {
        if (wakeLock !== null && document.visibilityState === 'visible' && isScannerHardwareActive) {
            await requestWakeLock();
        }
    });
});