document.addEventListener('DOMContentLoaded', function() {
    const addProductForm = document.getElementById('addProductFormAdmin');
    const viewSalesButton = document.getElementById('viewSalesBtn');
    const salesDataDiv = document.getElementById('salesDataDisplay');
    // CSRF token will be sourced from the form itself

    if (addProductForm) {
        addProductForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const csrfInput = form.querySelector('input[type="hidden"]'); // Assuming the first hidden input is CSRF
            const csrfTokenName = csrfInput ? csrfInput.name : null;
            const csrfTokenValue = csrfInput ? csrfInput.value : null;

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            if (csrfTokenName) {
                delete data[csrfTokenName]; // Remove CSRF from body if sent in header
            }

            try {
                const response = await fetch(window.BASE_PATH + '/api/products', { // API endpoint for adding products
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfTokenValue && {'X-CSRF-TOKEN': csrfTokenValue}) // Send CSRF if your API setup requires it
                    },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                
                if (result.success) {
                    if (window.popupNotification) window.popupNotification.success(result.message || "Product added successfully!", "Product Added");
                    form.reset();
                    // Optionally, refresh a product count or list if displayed on the dashboard
                } else {
                    if (window.popupNotification) window.popupNotification.error(result.message || "Failed to add product.", "Error");
                }
            } catch (error) {
                console.error("Add product error:", error);
                if (window.popupNotification) window.popupNotification.error("A server error occurred.", "Server Error");
            }
        });
    }

    if (viewSalesButton) {
        viewSalesButton.addEventListener('click', async () => {
            salesDataDiv.innerHTML = '<p class="text-center">Loading sales data...</p>';
            try {
                const response = await fetch(window.BASE_PATH + '/api/sales'); // API endpoint for sales (GET request, typically no CSRF needed)
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                
                if (result.success && result.data && result.data.length === 0) {
                    salesDataDiv.textContent = "No sales data available.";
                } else if (result.success && result.data) {
                    let html = '<ul>';
                    result.data.forEach(sale => {
                        // Ensure correct access to nested MongoDB date objects if applicable
                        const dateObject = sale.created_at && sale.created_at.$date ? sale.created_at.$date : sale.created_at;
                        const saleDate = dateObject ? new Date(dateObject).toLocaleDateString() : 'N/A';
                        const billId = sale._id && sale._id.$oid ? sale._id.$oid : (sale._id || 'N/A');
                        const displayBillId = billId !== 'N/A' ? billId.substr(-6) : 'N/A';

                        html += `<li>Bill #${displayBillId} - Amount: ₹${parseFloat(sale.total_amount).toFixed(2)} - Date: ${saleDate}</li>`;
                    });
                    html += '</ul>';
                    salesDataDiv.innerHTML = html;
                    if (window.popupNotification) window.popupNotification.success(`Loaded ${result.data.length} sales records.`, "Sales Data Loaded");
                } else {
                    throw new Error(result.message || "Invalid sales data received.");
                }
            } catch (error) {
                console.error("Fetch sales error:", error);
                salesDataDiv.textContent = "Failed to load sales data.";
                if (window.popupNotification) window.popupNotification.error("Failed to fetch sales data.", "Data Error");
            }
        });
    }
});
