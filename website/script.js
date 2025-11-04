// Global state
let cart = JSON.parse(localStorage.getItem('cart')) || [];
let menus = [];
let menuItems = [];
let currentFilter = {
    menuId: null,
    category: null,
    type: null
};

// Call Waiter Functionality
let selectedTable = null;

// Get table from URL parameter
function getTableFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    const tableNo = urlParams.get('table') || urlParams.get('tableno');
    return tableNo ? tableNo : null;
}

// Open Call Waiter Modal
document.getElementById('callWaiterBtn').addEventListener('click', () => {
    const tableFromURL = getTableFromURL();
    
    if (tableFromURL) {
        // If table is in URL, directly show confirmation
        showConfirmForTableFromURL(tableFromURL);
    } else {
        // Otherwise show table selection modal
        openCallWaiterModal();
    }
});

// Load table info from URL and show confirmation
async function showConfirmForTableFromURL(tableNumber) {
    try {
        const response = await fetch('../get_tables.php');
        const data = await response.json();
        
        if (data.success && data.data) {
            const table = data.data.find(t => t.table_number === tableNumber);
            
            if (table) {
                // Show confirmation popup directly
                showWaiterConfirmation(table.id, table.table_number, table.area_name);
            } else {
                // Table not found, show modal to select
                openCallWaiterModal();
            }
        } else {
            // Fallback to modal
            openCallWaiterModal();
        }
    } catch (error) {
        console.error('Error loading table:', error);
        openCallWaiterModal();
    }
}

// Close Modal
document.getElementById('closeWaiterModal').addEventListener('click', () => {
    document.getElementById('waiterModal').classList.remove('active');
});

// Load Tables for Modal
async function loadTables() {
    try {
        const response = await fetch('../get_tables.php');
        const data = await response.json();
        
        const tableGrid = document.getElementById('tableGrid');
        
        if (data.success && data.data) {
            tableGrid.innerHTML = data.data.map(table => `
                <div class="table-option" onclick="selectTable(${table.id}, '${table.table_number}', '${table.area_name}')">
                    <div class="table-number">${table.table_number}</div>
                    <div class="table-area">${table.area_name}</div>
                </div>
            `).join('');
        } else {
            tableGrid.innerHTML = '<p>No tables available</p>';
        }
    } catch (error) {
        console.error('Error loading tables:', error);
        document.getElementById('tableGrid').innerHTML = '<p>Error loading tables</p>';
    }
}

// Select Table
function selectTable(tableId, tableNumber, areaName) {
    selectedTable = { id: tableId, number: tableNumber, area: areaName };
    
    // Remove previous selection
    document.querySelectorAll('.table-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    
    // Add selection to clicked option
    event.currentTarget.classList.add('selected');
    
    // Show confirmation popup
    showWaiterConfirmation(tableId, tableNumber, areaName);
}

// Show Confirmation Popup
function showWaiterConfirmation(tableId, tableNumber, areaName) {
    const popup = document.createElement('div');
    popup.className = 'confirmation-popup';
    popup.innerHTML = `
        <div class="confirmation-content">
            <div class="confirmation-icon">🔔</div>
            <h3>Notify Waiter?</h3>
            <p>Do you want to notify the waiter for <strong>${tableNumber} - ${areaName}</strong>?</p>
            <div class="confirmation-buttons">
                <button class="btn-yes" onclick="confirmCallWaiter(${tableId}, '${tableNumber}', '${areaName}')">Yes, Notify</button>
                <button class="btn-no" onclick="closeConfirmationPopup(this)">Cancel</button>
            </div>
        </div>
    `;
    document.body.appendChild(popup);
    setTimeout(() => popup.classList.add('active'), 10);
}

// Close Confirmation Popup
function closeConfirmationPopup(btn) {
    const popup = btn.closest('.confirmation-popup');
    popup.classList.remove('active');
    setTimeout(() => popup.remove(), 300);
}

// Confirm and call waiter
async function confirmCallWaiter(tableId, tableNumber, areaName) {
    closeConfirmationPopup(event.currentTarget);
    
    // Show loading
    showNotification('Notifying waiter...', 'info');
    
    // Check if there are items in cart
    const hasCartItems = cart.length > 0;
    let notes = `Customer called waiter for table ${tableNumber}`;
    
    if (hasCartItems) {
        const cartItemsInfo = cart.map(item => `${item.quantity}x ${item.name}`).join(', ');
        notes = `Order request: ${cartItemsInfo}`;
        // Save cart items to localStorage for this table
        localStorage.setItem(`cart_for_table_${tableId}`, JSON.stringify(cart));
    }
    
    try {
        const response = await fetch('../create_waiter_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                table_id: tableId,
                request_type: hasCartItems ? 'Order' : 'General',
                notes: notes,
                has_items: hasCartItems ? 1 : 0
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('waiterModal').classList.remove('active');
            showNotification('Waiter has been notified! They will be with you shortly.', 'success');
            // Clear selection
            document.querySelectorAll('.table-option').forEach(opt => opt.classList.remove('selected'));
            
            // Clear cart after successful waiter call
            if (hasCartItems) {
                cart = [];
                updateCartStorage();
                updateCartUI();
            }
        } else {
            showNotification('Failed to notify waiter. Please try again.', 'error');
        }
    } catch (error) {
        console.error('Error calling waiter:', error);
        showNotification('Error calling waiter. Please try again.', 'error');
    }
}

// Show Notification
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span class="notification-icon">${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ⓘ'}</span>
        <span class="notification-message">${message}</span>
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.classList.add('active'), 10);
    setTimeout(() => {
        notification.classList.remove('active');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Open Modal
function openCallWaiterModal() {
    document.getElementById('waiterModal').classList.add('active');
    loadTables();
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadMenus();
    loadMenuItems();
    updateCartUI();
    
    // Event listeners
    document.getElementById('searchInput').addEventListener('input', handleSearch);
    document.getElementById('typeFilter').addEventListener('change', handleFilter);
    document.getElementById('categoryFilter').addEventListener('change', handleFilter);
    document.getElementById('cartIcon').addEventListener('click', toggleCart);
    document.getElementById('closeCart').addEventListener('click', toggleCart);
    document.getElementById('checkoutBtn').addEventListener('click', handleCheckout);
    
    // Close cart on overlay click
    document.getElementById('cartOverlay').addEventListener('click', toggleCart);
});

// Get restaurant ID from URL or use default
function getRestaurantId() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('restaurant_id') || 'RES001';
}

// Load menus
async function loadMenus() {
    try {
        const restaurantId = getRestaurantId();
        const response = await fetch(`api.php?action=getMenus&restaurant_id=${encodeURIComponent(restaurantId)}`);
        const data = await response.json();
        
        // Check for error
        if (data.error) {
            console.error('API Error:', data.error);
            document.getElementById('menuGrid').innerHTML = '<div class="loading">Error loading menu. Please check restaurant ID.</div>';
            return;
        }
        
        menus = Array.isArray(data) ? data : [];
        renderMenuTabs();
        
        // Load categories
        const catResponse = await fetch(`api.php?action=getCategories&restaurant_id=${encodeURIComponent(restaurantId)}`);
        const catData = await catResponse.json();
        const categories = Array.isArray(catData) ? catData : [];
        populateCategoryFilter(categories);
    } catch (error) {
        console.error('Error loading menus:', error);
        document.getElementById('menuGrid').innerHTML = '<div class="loading">Error loading menu. Please try again.</div>';
    }
}

// Render menu tabs
function renderMenuTabs() {
    const menuTabs = document.getElementById('menuTabs');
    const allBtn = menuTabs.querySelector('.category-btn');
    menuTabs.innerHTML = allBtn.outerHTML;
    
    menus.forEach(menu => {
        const btn = document.createElement('button');
        btn.className = 'category-btn';
        btn.textContent = menu.menu_name;
        btn.dataset.menuId = menu.id;
        btn.addEventListener('click', () => filterByMenu(menu.id));
        menuTabs.appendChild(btn);
    });
    
    // Make all button active
    menuTabs.querySelector('.category-btn').classList.add('active');
    menuTabs.querySelector('.category-btn').addEventListener('click', () => filterByMenu(null));
}

// Populate category filter
function populateCategoryFilter(categories) {
    const categoryFilter = document.getElementById('categoryFilter');
    categories.forEach(category => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        categoryFilter.appendChild(option);
    });
}

// Load menu items
async function loadMenuItems(filter = {}) {
    try {
        const restaurantId = getRestaurantId();
        let url = `api.php?action=getMenuItems&restaurant_id=${encodeURIComponent(restaurantId)}`;
        
        if (filter.menuId) url += `&menu_id=${filter.menuId}`;
        if (filter.category) url += `&category=${encodeURIComponent(filter.category)}`;
        if (filter.type) url += `&type=${encodeURIComponent(filter.type)}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        // Check for error
        if (data.error) {
            console.error('API Error:', data.error);
            menuItems = [];
            renderMenuItems();
            return;
        }
        
        menuItems = Array.isArray(data) ? data : [];
        renderMenuItems();
    } catch (error) {
        console.error('Error loading menu items:', error);
        menuItems = [];
        renderMenuItems();
    }
}

// Render menu items
function renderMenuItems() {
    const menuGrid = document.getElementById('menuGrid');
    
    if (menuItems.length === 0) {
        menuGrid.innerHTML = '<div class="loading">No items found</div>';
        return;
    }
    
    menuGrid.innerHTML = '';
    
    menuItems.forEach(item => {
        const card = createMenuItemCard(item);
        menuGrid.appendChild(card);
    });
}

// Create menu item card
function createMenuItemCard(item) {
    const card = document.createElement('div');
    card.className = 'menu-card';
    
    const typeBadge = item.item_type === 'Veg' ? '🌱 Veg' : 
                      item.item_type === 'Non Veg' ? '🍖 Non Veg' :
                      item.item_type === 'Egg' ? '🥚 Egg' :
                      item.item_type === 'Drink' ? '🥤 Drink' : '';
    
    const imageUrl = item.item_image ? `image.php?path=${encodeURIComponent(item.item_image)}` : '';
    
    card.innerHTML = `
        <div class="menu-card-image">
            ${imageUrl ? `<img src="${imageUrl}" alt="${item.item_name_en}" style="width: 100%; height: 100%; object-fit: cover;">` : '🍽️'}
        </div>
        <div class="menu-card-content">
            <div class="menu-card-header">
                <div class="menu-card-name">${item.item_name_en}</div>
                <div class="menu-type-badge">${typeBadge}</div>
            </div>
            <div class="menu-card-description">${item.item_description_en || 'Delicious food item'}</div>
            <div class="menu-card-footer">
                <div class="menu-card-price">₹${parseFloat(item.base_price).toFixed(2)}</div>
                <button class="add-to-cart-btn" onclick="addToCart(${item.id}, '${item.item_name_en}', ${item.base_price}, '${item.item_image || ''}')">
                    <span class="material-symbols-rounded">add_shopping_cart</span>
                    Add
                </button>
            </div>
        </div>
    `;
    
    return card;
}

// Add to cart
function addToCart(itemId, itemName, itemPrice, itemImage) {
    const existingItem = cart.find(item => item.id === itemId);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: itemId,
            name: itemName,
            price: parseFloat(itemPrice),
            image: itemImage,
            quantity: 1
        });
    }
    
    updateCartStorage();
    updateCartUI();
    showCartNotification();
}

// Update cart storage
function updateCartStorage() {
    localStorage.setItem('cart', JSON.stringify(cart));
}

// Update cart UI
function updateCartUI() {
    // Update cart count
    const cartCount = document.getElementById('cartCount');
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    cartCount.textContent = totalItems;
    
    // Render cart items
    renderCartItems();
    
    // Update total
    const cartTotal = document.getElementById('cartTotal');
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    cartTotal.textContent = total.toFixed(2);
    
    // Check if table is in URL
    const tableFromURL = getTableFromURL();
    const continueSection = document.getElementById('continueSection');
    const checkoutBtn = document.getElementById('checkoutBtn');
    
    if (tableFromURL && cart.length > 0) {
        // Show Continue button instead of Checkout
        continueSection.style.display = 'block';
        checkoutBtn.style.display = 'none';
        checkoutBtn.disabled = true;
    } else {
        // Show regular checkout for website users
        continueSection.style.display = 'none';
        checkoutBtn.style.display = 'block';
        checkoutBtn.disabled = cart.length === 0;
    }
}

// Complete Selection and show Call Waiter button
function completeSelection() {
    const callWaiterAction = document.getElementById('callWaiterAction');
    if (callWaiterAction.style.display === 'none') {
        callWaiterAction.style.display = 'block';
        // Scroll to show the button
        callWaiterAction.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// Trigger Call Waiter from cart
function triggerCallWaiter() {
    const tableFromURL = getTableFromURL();
    if (tableFromURL) {
        // Close cart first
        toggleCart();
        // Then trigger call waiter
        setTimeout(() => {
            showConfirmForTableFromURL(tableFromURL);
        }, 300);
    }
}

// Render cart items
function renderCartItems() {
    const cartItems = document.getElementById('cartItems');
    
    if (cart.length === 0) {
        cartItems.innerHTML = `
            <div class="empty-cart">
                <span class="material-symbols-rounded">shopping_cart</span>
                <p>Your cart is empty</p>
            </div>
        `;
        return;
    }
    
    cartItems.innerHTML = '';
    
    cart.forEach((item, index) => {
        const cartItem = createCartItemHTML(item, index);
        cartItems.appendChild(cartItem);
    });
}

// Create cart item HTML
function createCartItemHTML(item, index) {
    const div = document.createElement('div');
    div.className = 'cart-item';
    
    const imageUrl = item.image ? `image.php?path=${encodeURIComponent(item.image)}` : '';
    
    div.innerHTML = `
        <div class="cart-item-image">
            ${imageUrl ? `<img src="${imageUrl}" alt="${item.name}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">` : '🍽️'}
        </div>
        <div class="cart-item-info">
            <div class="cart-item-name">${item.name}</div>
            <div class="cart-item-price">₹${item.price.toFixed(2)}</div>
            <div class="cart-item-controls">
                <button class="quantity-btn" onclick="updateQuantity(${index}, -1)">-</button>
                <input type="number" class="quantity-input" value="${item.quantity}" min="1" 
                       onchange="updateQuantityInput(${index}, this.value)">
                <button class="quantity-btn" onclick="updateQuantity(${index}, 1)">+</button>
                <button class="remove-item" onclick="removeFromCart(${index})">
                    <span class="material-symbols-rounded">delete</span>
                </button>
            </div>
        </div>
    `;
    
    return div;
}

// Update quantity
function updateQuantity(index, change) {
    cart[index].quantity = Math.max(1, cart[index].quantity + change);
    updateCartStorage();
    updateCartUI();
}

// Update quantity from input
function updateQuantityInput(index, value) {
    cart[index].quantity = Math.max(1, parseInt(value) || 1);
    updateCartStorage();
    updateCartUI();
}

// Remove from cart
function removeFromCart(index) {
    cart.splice(index, 1);
    updateCartStorage();
    updateCartUI();
}

// Toggle cart
function toggleCart() {
    const cartSidebar = document.getElementById('cartSidebar');
    const cartOverlay = document.getElementById('cartOverlay');
    
    cartSidebar.classList.toggle('open');
    cartOverlay.classList.toggle('show');
}

// Show cart notification
function showCartNotification() {
    // Add notification animation
    const cartIcon = document.getElementById('cartIcon');
    cartIcon.style.transform = 'scale(1.2)';
    setTimeout(() => {
        cartIcon.style.transform = 'scale(1)';
    }, 300);
}

// Handle search
let searchTimeout;
function handleSearch(e) {
    const searchTerm = e.target.value.trim();
    
    clearTimeout(searchTimeout);
    
    if (searchTerm.length < 2) {
        loadMenuItems(currentFilter);
        return;
    }
    
    searchTimeout = setTimeout(async () => {
        try {
            const restaurantId = getRestaurantId();
            const response = await fetch(`api.php?action=searchItems&q=${encodeURIComponent(searchTerm)}&restaurant_id=${encodeURIComponent(restaurantId)}`);
            const data = await response.json();
            
            // Check for error
            if (data.error) {
                console.error('Search Error:', data.error);
                menuItems = [];
            } else {
                menuItems = Array.isArray(data) ? data : [];
            }
            renderMenuItems();
        } catch (error) {
            console.error('Search error:', error);
            menuItems = [];
            renderMenuItems();
        }
    }, 300);
}

// Handle filter
function handleFilter() {
    const type = document.getElementById('typeFilter').value;
    const category = document.getElementById('categoryFilter').value;
    
    currentFilter.type = type;
    currentFilter.category = category;
    
    loadMenuItems(currentFilter);
}

// Filter by menu
function filterByMenu(menuId) {
    // Update active button
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    currentFilter.menuId = menuId;
    loadMenuItems(currentFilter);
}

// Clear filters
function clearFilters() {
    document.getElementById('typeFilter').value = '';
    document.getElementById('categoryFilter').value = '';
    
    const allBtn = document.querySelector('.category-btn');
    if (allBtn) {
        document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
        allBtn.classList.add('active');
    }
    
    currentFilter = {
        menuId: null,
        category: null,
        type: null
    };
    
    loadMenuItems();
}

// Handle checkout
function handleCheckout() {
    if (cart.length === 0) return;
    
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    
    // Close cart and show customer details modal
    toggleCart();
    showCustomerDetailsModal(total);
}

// Show Customer Details Modal
function showCustomerDetailsModal(total) {
    // Remove existing modal if it exists
    const existingModal = document.getElementById('customerModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Load saved customer data
    const savedCustomer = loadCustomerFromStorage();
    
    // Create modal
    const modal = document.createElement('div');
    modal.id = 'customerModal';
    modal.className = 'customer-modal';
    modal.innerHTML = `
        <div class="customer-modal-content">
            <div class="customer-modal-header">
                <h2>Customer Details</h2>
                <button class="close-modal" id="closeCustomerModal">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <div class="customer-modal-body">
                <form id="customerForm">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" id="customerName" required placeholder="Enter your name" value="${savedCustomer?.name || ''}">
                    </div>
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="tel" id="customerPhone" required placeholder="Enter your phone number" value="${savedCustomer?.phone || ''}">
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" id="customerEmail" placeholder="Enter your email (optional)" value="${savedCustomer?.email || ''}">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea id="customerAddress" rows="3" placeholder="Enter delivery address (optional)">${savedCustomer?.address || ''}</textarea>
                    </div>
                    <div class="form-group remember-section">
                        <label class="remember-label">
                            <input type="checkbox" id="rememberCustomer" checked>
                            <span>Remember my details for next time</span>
                        </label>
                    </div>
                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        <div class="summary-items" id="summaryItems"></div>
                        <div class="summary-total">
                            <strong>Total: ₹<span id="summaryTotal">0.00</span></strong>
                        </div>
                    </div>
                    <div class="payment-method-section" id="paymentSection">
                        <h3 style="margin-bottom: 1rem;">Payment Method</h3>
                        <div class="payment-options">
                            <label class="payment-option">
                                <input type="radio" name="paymentMethod" value="Cash" checked>
                                <div class="payment-option-content">
                                    <span class="material-symbols-rounded">monetization_on</span>
                                    <span>Cash</span>
                                </div>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="paymentMethod" value="PhonePe">
                                <div class="payment-option-content">
                                    <span class="material-symbols-rounded">account_balance_wallet</span>
                                    <span>PhonePe</span>
                                </div>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="paymentMethod" value="UPI">
                                <div class="payment-option-content">
                                    <span class="material-symbols-rounded">qr_code</span>
                                    <span>UPI</span>
                                </div>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="paymentMethod" value="Card">
                                <div class="payment-option-content">
                                    <span class="material-symbols-rounded">credit_card</span>
                                    <span>Card</span>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div class="customer-modal-actions">
                        <button type="button" class="btn-cancel" onclick="closeCustomerModal()">Cancel</button>
                        <button type="submit" class="btn-submit">Place Order</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Close button
    document.getElementById('closeCustomerModal').addEventListener('click', closeCustomerModal);
    
    // Form submit
    document.getElementById('customerForm').addEventListener('submit', function(e) {
        e.preventDefault();
        processOrder(total);
    });
    
    // Update order summary
    updateOrderSummary();
    
    // Show modal
    setTimeout(() => {
        modal.classList.add('active');
    }, 10);
}

// Update Order Summary
function updateOrderSummary() {
    const summaryItems = document.getElementById('summaryItems');
    if (summaryItems) {
        summaryItems.innerHTML = cart.map(item => `
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>${item.name} x ${item.quantity}</span>
                <span>₹${(item.price * item.quantity).toFixed(2)}</span>
            </div>
        `).join('');
    }
    
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const summaryTotal = document.getElementById('summaryTotal');
    if (summaryTotal) {
        summaryTotal.textContent = total.toFixed(2);
    }
}

// Close Customer Modal
function closeCustomerModal() {
    const modal = document.getElementById('customerModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Save Customer to LocalStorage
function saveCustomerToStorage(customerData) {
    localStorage.setItem('customerDetails', JSON.stringify(customerData));
}

// Load Customer from LocalStorage
function loadCustomerFromStorage() {
    const saved = localStorage.getItem('customerDetails');
    return saved ? JSON.parse(saved) : null;
}

// Clear Saved Customer
function clearCustomerFromStorage() {
    localStorage.removeItem('customerDetails');
}

// Process Order
async function processOrder(total) {
    const name = document.getElementById('customerName').value;
    const phone = document.getElementById('customerPhone').value;
    const email = document.getElementById('customerEmail').value || '';
    const address = document.getElementById('customerAddress').value || '';
    const remember = document.getElementById('rememberCustomer').checked;
    
    // Save customer details if remember is checked
    if (remember) {
        saveCustomerToStorage({ name, phone, email, address });
    } else {
        clearCustomerFromStorage();
    }
    
    // Get selected payment method
    const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
    
    try {
        const response = await fetch('../process_website_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                customer_name: name,
                customer_phone: phone,
                customer_email: email,
                customer_address: address,
                items: cart,
                total: total,
                payment_method: paymentMethod
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Order placed successfully! Order #' + data.order_number, 'success');
            closeCustomerModal();
            
            // Clear cart
            cart = [];
            updateCartStorage();
            updateCartUI();
        } else {
            showNotification('Failed to place order: ' + (data.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error placing order:', error);
        showNotification('Error placing order. Please try again.', 'error');
    }
}

