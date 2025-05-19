<x-app-layout :pageTitle="$pageTitle ?? 'Bill History'">
    <h1 class="page-title">{{ $pageTitle }}</h1>
    <section class="content-section glass">
        <h2 class="section-title">Search Bills</h2>
        <input type="text" id="billSearch" class="bill-search-input" placeholder="Search by Product, Bill ID, or Biller Name...">
        <div id="billList" class="card-list mt-3">
            <p class="text-center text-light">Loading bill history...</p>
        </div>
    </section>

    <div id="billDetailsModal" class="bill-details-modal-backdrop" style="display:none;">
        <div class="bill-details-modal-content glass">
            <button class="bill-details-modal-close-btn" onclick="window.closeModal()">✕</button>
            <div id="billDetailsContent"></div>
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('js/staff-bill-view.js') }}?v={{ filemtime(public_path('js/staff-bill-view.js')) }}"></script>
    @endpush
</x-app-layout>