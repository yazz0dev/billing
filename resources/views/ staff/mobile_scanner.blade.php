<x-minimal-layout :pageTitle="$pageTitle ?? 'Mobile Scanner'" :bodyClass="$bodyClass ?? 'layout-minimal'">
    <style>
        /* ... (styles specific to mobile_scanner, same as your PHP template) ... */
        .scanner-container { /* ... */ }
        .scanner-page-header { /* ... */ }
        /* ... etc ... */
    </style>

    <div class="scanner-container">
        <div class="scanner-page-header">
            <h1 class="page-title" style="font-size: 1.4rem; margin-bottom: 0; text-align: left; background: none; color: var(--text-heading); transform: none; left: auto; padding-bottom: 0;">Mobile Scanner</h1>
            <span class="user-info-scanner">User: <strong id="mobileUsernameDisplay">{{ Auth::user()->username ?? 'N/A' }}</strong></span>
        </div>
        
        <div id="activationInfo" class="glass p-3" style="border-left-width: 4px;">
            <p id="activationStatusMessage">Checking activation status with POS terminal...</p>
            <button id="tryActivateScannerBtn" class="btn mt-2" style="display:none;">Try Activating Scanner</button>
        </div>

        <div id="scanner-region" style="display:none;"></div>
        <div id="statusMessage" class="status-message info" style="display:none;"></div>
        <div id="lastScannedProduct" class="product-info" style="display:block;">Last: N/A</div>

        <div class="controls" style="display:none;" id="scanControls">
            <button id="stopScannerBtn" class="btn">Stop Camera</button>
        </div>

        <div id="recentScans" class="glass p-2 mt-2" style="display:none;">
            <h4 style="font-size: 0.9rem; margin-bottom: 5px;">Recent Scans:</h4>
            <ul id="recentScansList" style="list-style: none; padding: 0;"></ul>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="mt-5 text-center">
            @csrf
            <button type="submit" class="btn" style="background-color: var(--error); font-size: 0.9rem;">Logout</button>
        </form>
    </div>

    @push('scripts')
        {{-- Ensure html5-qrcode.min.js is in public/js --}}
        <script src="{{ asset('js/html5-qrcode.min.js') }}"></script> 
        <script src="{{ asset('js/mobile-scanner.js') }}?v={{ filemtime(public_path('js/mobile-scanner.js')) }}"></script>
    @endpush
</x-minimal-layout>