<?php
session_start();

// Check if user is staff or admin (already logged in on mobile)
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['staff', 'admin'])) {
    header('Location: /billing/login.php?error=unauthorized_mobile_pair');
    exit;
}

$pageTitle = "Pair Mobile Scanner";
$bodyClass = "mobile-pair-page";
// No main topbar for this simple page, but needs global.css
$hideTopbar = true; 

// We need header for CSS and potentially footer for scripts
require_once '../includes/header.php'; // This will output HTML doctype, head etc.
?>

<div class="scanner-container" style="padding-top: 20px;">
    <h1 class="page-title" style="font-size: 1.5rem; margin-bottom: 1rem;">Pair with Desktop</h1>
    <p style="text-align: center; margin-bottom: 1rem;">
        User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
    </p>
    <p style="text-align: center; margin-bottom: 1.5rem;">
        Scan the QR code displayed on your desktop POS screen to link this mobile device as a barcode scanner.
    </p>

    <div id="mobile-qr-reader" style="width: 100%; max-width: 300px; margin: 0 auto 20px auto; border: 1px solid var(--primary);"></div>
    <button id="startQrScanBtn" class="btn w-full">Start Camera to Scan Desktop QR</button>
    <button id="stopQrScanBtn" class="btn w-full" style="display:none; background-color: var(--warning); margin-top:10px;">Stop Camera</button>
    
    <div id="pairingStatus" class="status-message info" style="display:none; margin-top: 15px;"></div>

    <div style="margin-top: 20px; text-align: center;">
        <a href="/billing/staff/index.html" id="goToScannerLink" class="btn" style="display:none; background-color: var(--success);">Go to Barcode Scanner</a>
        <a href="/billing/logout.php" class="btn" style="background-color: var(--error); margin-top: 10px;">Logout</a>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mobileQrReaderDiv = document.getElementById('mobile-qr-reader');
    const startQrScanBtn = document.getElementById('startQrScanBtn');
    const stopQrScanBtn = document.getElementById('stopQrScanBtn');
    const pairingStatusDiv = document.getElementById('pairingStatus');
    const goToScannerLink = document.getElementById('goToScannerLink');
    let html5QrCodePairing = null;
    let isPairingScannerActive = false;

    function displayPairingStatus(message, type = 'info') {
        pairingStatusDiv.textContent = message;
        pairingStatusDiv.className = `status-message ${type}`;
        pairingStatusDiv.style.display = 'block';
    }

    startQrScanBtn.addEventListener('click', () => {
        if (isPairingScannerActive) return;
        startQrScanBtn.style.display = 'none';
        stopQrScanBtn.style.display = 'block';
        mobileQrReaderDiv.style.display = 'block';
        displayPairingStatus('Starting camera...', 'info');

        html5QrCodePairing = new Html5Qrcode("mobile-qr-reader");
        const config = { fps: 5, qrbox: { width: 250, height: 150 } };

        html5QrCodePairing.start({ facingMode: "environment" }, config, 
            (decodedText, decodedResult) => { // onScanSuccess
                stopPairingScanner(); // Stop immediately after a successful scan
                displayPairingStatus(`QR Scanned: ${decodedText}. Attempting to pair...`, 'info');
                
                try {
                    const url = new URL(decodedText);
                    const pairingToken = url.searchParams.get("token");

                    if (pairingToken) {
                        confirmPairingWithServer(pairingToken);
                    } else {
                        displayPairingStatus('Invalid QR code: No pairing token found.', 'error');
                        startQrScanBtn.style.display = 'block'; // Allow retry
                    }
                } catch (e) {
                    displayPairingStatus('Scanned content is not a valid URL for pairing.', 'error');
                    console.error("QR scan content error:", e, decodedText);
                    startQrScanBtn.style.display = 'block'; // Allow retry
                }
            }, 
            (errorMessage) => { // onScanFailure - usually not needed to display constantly
                // console.warn(`Pairing QR scan error: ${errorMessage}`);
            }
        ).then(() => {
            isPairingScannerActive = true;
            displayPairingStatus('Camera active. Scan QR on desktop.', 'info');
        }).catch(err => {
            displayPairingStatus(`Error starting camera: ${err}`, 'error');
            console.error("Pairing QR camera start error:", err);
            startQrScanBtn.style.display = 'block';
            stopQrScanBtn.style.display = 'none';
            mobileQrReaderDiv.style.display = 'none';
        });
    });

    stopQrScanBtn.addEventListener('click', stopPairingScanner);

    function stopPairingScanner() {
        if (html5QrCodePairing && isPairingScannerActive) {
            html5QrCodePairing.stop()
                .then(() => {
                    isPairingScannerActive = false;
                    displayPairingStatus('Camera stopped.', 'info');
                    mobileQrReaderDiv.style.display = 'none';
                    startQrScanBtn.style.display = 'block';
                    stopQrScanBtn.style.display = 'none';
                    if (html5QrCodePairing) html5QrCodePairing.clear();
                }).catch(err => {
                    displayPairingStatus(`Error stopping camera: ${err}`, 'error');
                });
        } else {
            isPairingScannerActive = false;
            mobileQrReaderDiv.style.display = 'none';
            startQrScanBtn.style.display = 'block';
            stopQrScanBtn.style.display = 'none';
        }
    }

    function confirmPairingWithServer(token) {
        const formData = new FormData();
        formData.append('action', 'confirmMobilePairing');
        formData.append('pairing_token', token);

        fetch('/billing/server.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPairingStatus('Successfully paired with desktop! You can now use the barcode scanner.', 'success');
                goToScannerLink.style.display = 'inline-block';
                startQrScanBtn.style.display = 'none'; // Hide scan button as pairing is done
                stopQrScanBtn.style.display = 'none';
                // Optionally, automatically redirect:
                // window.location.href = '/billing/staff/index.html';
            } else {
                displayPairingStatus(`Pairing failed: ${data.message || 'Unknown error.'}`, 'error');
                startQrScanBtn.style.display = 'block'; // Allow retry
            }
        })
        .catch(error => {
            displayPairingStatus(`Network error during pairing: ${error.message}`, 'error');
            console.error('Pairing confirmation error:', error);
            startQrScanBtn.style.display = 'block'; // Allow retry
        });
    }
});
</script>

<?php
// We need footer if header was used
require_once '../includes/footer.php'; 
?>