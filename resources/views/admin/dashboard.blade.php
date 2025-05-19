<x-app-layout :pageTitle="$pageTitle ?? 'Admin Dashboard'">
    {{-- <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $pageTitle }}
        </h2>
    </x-slot> --}}

    <h1 class="page-title">{{ $pageTitle }}</h1>

    <section class="content-section glass">
        <h2 class="section-title">Overview</h2>
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="stat-card card-base" style="text-align: center;">
                <h3>Total Sales Orders</h3>
                <p class="stat-value" style="font-size: 2rem; font-weight: bold; margin-top: 0.5rem;">{{ $totalSales ?? 0 }}</p>
            </div>
            <div class="stat-card card-base" style="text-align: center;">
                <h3>Total Products</h3>
                <p class="stat-value" style="font-size: 2rem; font-weight: bold; margin-top: 0.5rem;">{{ $totalProducts ?? 0 }}</p>
            </div>
        </div>
    </section>

    <section class="content-section glass">
        <h2 class="section-title">Manage Products (Quick Add)</h2>
        <form id="addProductFormAdmin"> {{-- CSRF token is handled by X-CSRF-TOKEN header in JS for API calls --}}
            <div class="form-group">
                <label for="adminProductName">Product Name</label>
                <input type="text" id="adminProductName" name="name" placeholder="Enter product name" required>
            </div>
            <div class="form-group">
                <label for="adminProductPrice">Price (₹)</label>
                <input type="number" id="adminProductPrice" name="price" placeholder="0.00" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="adminProductStock">Stock Quantity</label>
                <input type="number" id="adminProductStock" name="stock" placeholder="0" min="0" required>
            </div>
            <button type="submit" class="btn w-full">Add Product</button>
        </form>
    </section>

    <section class="content-section glass">
        <h2 class="section-title">Sales Data</h2>
        <button id="viewSalesBtn" class="btn">View All Sales</button>
        <div id="salesDataDisplay" class="mt-3" style="max-height: 300px; overflow-y: auto; background: var(--bg-surface-alt); padding: 10px; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color-subtle);">
            <p class="text-center">Click "View All Sales" to load data.</p>
        </div>
    </section>

    @push('scripts')
    <script src="{{ asset('js/admin-dashboard.js') }}?v={{ filemtime(public_path('js/admin-dashboard.js')) }}"></script>
    @endpush
</x-app-layout>