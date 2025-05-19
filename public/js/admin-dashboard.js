document.addEventListener('DOMContentLoaded', function() {
    const addProductForm = document.getElementById('addProductFormAdmin');
    const viewSalesButton = document.getElementById('viewSalesBtn');
    const salesDataDiv = document.getElementById('salesDataDisplay');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (addProductForm) {
        addProductForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch(`${window.APP_URL}/api/products`, { // Use APP_URL
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken && {'X-CSRF-TOKEN': csrfToken})
                    },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                
                if (result.success) {
                    if (window.popupNotification) window.popupNotification.success(result.message || "Product added successfully!", "Product Added");
                    form.reset();
                    // Optionally, update product count on dashboard or trigger a list refresh
                } else {
                    let errorMessage = result.message || "Failed to add product.";
                    if (result.errors) {
                        errorMessage += " Details: " + Object.values(result.errors).flat().join(' ');
                    }
                    if (window.popupNotification) window.popupNotification.error(errorMessage, "Error");
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
                const response = await fetch(`${window.APP_URL}/api/sales`, { // Use APP_URL
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken && {'X-CSRF-TOKEN': csrfToken}) // GET requests might not need CSRF, but good practice if middleware checks
                    }
                });
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                
                if (result.success && result.data && result.data.length === 0) {
                    salesDataDiv.textContent = "No sales data available.";
                } else if (result.success && result.data) {
                    let html = '<ul>';
                    result.data.forEach(sale => {
                        const saleDate = sale.created_at ? new Date(sale.created_at).toLocaleDateString() : 'N/A';
                        const billId = sale._id || sale.id || 'N/A'; // Eloquent might return 'id'
                        const displayBillId = billId !== 'N/A' ? String(billId).substr(-6) : 'N/A';

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