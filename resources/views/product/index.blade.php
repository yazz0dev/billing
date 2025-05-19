<x-app-layout :pageTitle="$pageTitle ?? 'Product Management'">
    <h1 class="page-title">{{ $pageTitle }}</h1>

    <section class="content-section glass">
        <h2 class="section-title">Add New Product</h2>
        <form id="addProductForm"> {{-- CSRF for API calls in JS --}}
            <div class="flex flex-col md:flex-row gap-2">
                <div class="form-group flex-grow">
                    <label for="productName">Product Name</label>
                    <input type="text" id="productName" name="name" placeholder="Enter product name" required>
                </div>
                <div class="form-group">
                    <label for="productPrice">Price (₹)</label>
                    <input type="number" id="productPrice" name="price" placeholder="0.00" min="0.01" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="productStock">Stock</label>
                    <input type="number" id="productStock" name="stock" placeholder="0" min="0" step="1" required>
                </div>
            </div>
             {{-- Optional: Add fields for barcode, low_stock_threshold here if needed in this form --}}
            <button type="submit" class="btn">Add Product</button>
        </form>
    </section>

    <section class="content-section glass mt-4">
        <h2 class="section-title">Product List</h2>
        <div id="productList" class="product-grid">
            <p class="text-center text-light">Loading products...</p>
        </div>
    </section>

    @push('scripts')
    <script src="{{ asset('js/admin-product-management.js') }}?v={{ filemtime(public_path('js/admin-product-management.js')) }}"></script>
    @endpush
</x-app-layout>