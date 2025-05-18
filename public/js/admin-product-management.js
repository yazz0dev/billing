// public/js/admin-product-management.js
document.addEventListener('DOMContentLoaded', async function() {
    const productListDiv = document.getElementById('productList');
    const addProductForm = document.getElementById('addProductForm'); // Ensure this ID is on the form in product/index.php template
    
    // CSRF Token (assuming it's in a hidden input rendered by PHP)
    // The token name might be different based on what you set in ProductController
    const csrfTokenInput = document.querySelector('input[name="product_action_csrf"]'); 
    const csrfToken = csrfTokenInput ? csrfTokenInput.value : null;

    async function loadProducts() {
        try {
            const response = await fetch(window.BASE_PATH + '/api/products'); // GET request
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();

            if (result.success && result.data && result.data.length > 0) {
                productListDiv.innerHTML = result.data.map(product => `
                    <div class="product-card card-base">
                        <h3>${product.name}</h3>
                        <p class="card-meta">Stock: ${product.stock} units</p>
                        <p class="mb-3">Price: ₹${parseFloat(product.price).toFixed(2)}</p>
                        <div class="card-actions">
                            <button class="btn" onclick="editProduct('${product._id.$oid || product._id}')">Edit</button>
                            <button class="btn btn-danger" 
                                onclick="deleteProduct('${product._id.$oid || product._id}')">Delete</button>
                        </div>
                    </div>
                `).join('');
            } else {
                productListDiv.innerHTML = '<p class="text-center text-light glass p-3">No products found.</p>';
            }
        } catch (error) {
            console.error("Failed to fetch products:", error);
            productListDiv.innerHTML = '<p class="text-center text-light glass p-3">Could not load products.</p>';
            if (window.popupNotification) window.popupNotification.error("Failed to load products.", "Data Error");
        }
    }

    if (addProductForm) {
        addProductForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            delete data['product_action_csrf']; // Remove CSRF from body if sent in header

            try {
                const response = await fetch(window.BASE_PATH + '/api/products', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken && {'X-CSRF-TOKEN': csrfToken}) // Add CSRF token header
                    },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                
                if (result.success) {
                    if(window.popupNotification) window.popupNotification.success("Product added successfully!");
                    addProductForm.reset();
                    await loadProducts();
                } else {
                    if(window.popupNotification) window.popupNotification.error("Failed to add product: " + (result.message || "Unknown error"));
                }
            } catch (error) {
                console.error("Error adding product:", error);
                if(window.popupNotification) window.popupNotification.error("An error occurred while adding product.");
            }
        });
    }

    window.editProduct = async function(productId) {
        try {
            const productResponse = await fetch(window.BASE_PATH + `/api/products/${productId}`);
            if (!productResponse.ok) {
                 if(window.popupNotification) window.popupNotification.error("Product not found or error fetching.");
                 return;
            }
            const productResult = await productResponse.json();
            const product = productResult.data;

            if (product) {
                const name = prompt("Product Name:", product.name);
                const price = prompt("Price:", product.price);
                const stock = prompt("Stock:", product.stock);

                if (name !== null && price !== null && stock !== null) { // Check for null if user cancels prompt
                    const updatedProduct = { name, price: parseFloat(price), stock: parseInt(stock) };
                    
                    const updateResponse = await fetch(window.BASE_PATH + `/api/products/${productId}`, {
                        method: 'PUT', // Or PATCH
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(csrfToken && {'X-CSRF-TOKEN': csrfToken})
                        },
                        body: JSON.stringify(updatedProduct)
                    });
                    const result = await updateResponse.json();
                    if (result.success) {
                        if(window.popupNotification) window.popupNotification.success("Product updated!");
                        await loadProducts();
                    } else {
                        if(window.popupNotification) window.popupNotification.error("Failed to update product: " + (result.message || ""));
                    }
                }
            }
        } catch (error) {
            console.error("Error editing product:", error);
            if(window.popupNotification) window.popupNotification.error("An error occurred during edit.");
        }
    };

    window.deleteProduct = async function(productId) {
        if (!confirm("Are you sure you want to delete this product?")) return;
        try {
            const response = await fetch(window.BASE_PATH + `/api/products/${productId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken && {'X-CSRF-TOKEN': csrfToken})
                }
            });
            const result = await response.json();
            if (result.success) {
                if(window.popupNotification) window.popupNotification.success("Product deleted!");
                await loadProducts();
            } else {
                if(window.popupNotification) window.popupNotification.error("Failed to delete product: " + (result.message || ""));
            }
        } catch (error) {
            console.error("Error deleting product:", error);
            if(window.popupNotification) window.popupNotification.error("An error occurred during deletion.");
        }
    };

    // Initial load
    if (productListDiv) { // Only load if the product list element exists on the page
        await loadProducts();
    }
});
