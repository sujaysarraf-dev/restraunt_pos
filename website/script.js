// Global state
let cart = JSON.parse(localStorage.getItem('cart')) || [];
let menus = [];
let menuItems = [];
let currentFilter = {
    menuId: null,
    category: null,
    type: null
};

// Currency symbol from server-side (set in index.php head)
// Use window.globalCurrencySymbol if available, otherwise fallback to localStorage or default
const globalCurrencySymbol = window.globalCurrencySymbol || localStorage.getItem('system_currency') || '₹';

// Format currency helper function - uses server-side currency symbol
function formatCurrency(amount) {
    const symbol = globalCurrencySymbol || '₹';
    return `${symbol}${parseFloat(amount).toFixed(2)}`;
}

// Format currency without decimals (for summary bar)
function formatCurrencyNoDecimals(amount) {
    const symbol = globalCurrencySymbol || '₹';
    return `${symbol}${parseFloat(amount).toFixed(0)}`;
}

// Call Waiter Functionality
let selectedTable = null;

// Reservation capacity tracking
let selectedTableCapacity = null;

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
        const restaurantId = getRestaurantId();
        const response = await fetch(`../get_tables.php?restaurant_id=${restaurantId}`);
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
        const restaurantId = getRestaurantId();
        const response = await fetch(`../get_tables.php?restaurant_id=${restaurantId}`);
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
                
                // Ensure cart summary bar is hidden
                const cartSummaryBar = document.getElementById('cartSummaryBar');
                if (cartSummaryBar) {
                    cartSummaryBar.style.display = 'none';
                    cartSummaryBar.classList.remove('show');
                }
                document.body.classList.remove('has-cart');
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

// Reservation Functionality
// Open Reservation Modal
function openReservationModal() {
    document.getElementById('reservationModal').classList.add('active');
    // Show table selection, hide form
    document.getElementById('reservationTableSelection').style.display = 'block';
    document.getElementById('reservationFormSection').style.display = 'none';
    loadReservationTables();
    // Set minimum date to today
    const dateInput = document.getElementById('reservationDate');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);
        if (!dateInput.value) {
            dateInput.value = today;
        }
    }
}

// Load Tables for Reservation Modal
async function loadReservationTables() {
    try {
        const restaurantId = getRestaurantId();
        const response = await fetch(`../get_tables.php?restaurant_id=${restaurantId}`);
        const data = await response.json();
        
        const tableGrid = document.getElementById('reservationTableGrid');
        
        if (!tableGrid) {
            console.error('Reservation table grid not found');
            return;
        }
        
        if (data.success && data.data) {
            tableGrid.innerHTML = data.data.map(table => `
                <div class="table-option" onclick="selectReservationTable(${table.id}, '${table.table_number}', '${table.area_name || ''}', ${table.capacity || 4})">
                    <div class="table-number">${table.table_number}</div>
                    <div class="table-area">${table.area_name || 'Unknown Area'}</div>
                    <div style="font-size: 0.85rem; margin-top: 0.5rem; opacity: 0.8;">Capacity: ${table.capacity || 4} seats</div>
                </div>
            `).join('');
        } else {
            tableGrid.innerHTML = '<p>No tables available</p>';
        }
    } catch (error) {
        console.error('Error loading tables:', error);
        const tableGrid = document.getElementById('reservationTableGrid');
        if (tableGrid) {
            tableGrid.innerHTML = '<p>Error loading tables</p>';
        }
    }
}

// Select Table for Reservation
function selectReservationTable(tableId, tableNumber, areaName, capacity) {
    // Store selected table info
    document.getElementById('selectedTableId').value = tableId;
    selectedTableCapacity = capacity || 4;
    
    // Update guests input max and show capacity info
    const guestsInput = document.getElementById('reservationGuests');
    if (guestsInput) {
        guestsInput.setAttribute('max', selectedTableCapacity);
        guestsInput.value = Math.min(parseInt(guestsInput.value) || 1, selectedTableCapacity);
    }
    
    // Show capacity info
    const capacityInfo = document.getElementById('tableCapacityInfo');
    if (capacityInfo) {
        capacityInfo.innerHTML = `<span class="material-symbols-rounded" style="font-size: 1.2rem; vertical-align: middle;">table_restaurant</span> Table ${tableNumber} - ${areaName} <strong>(Max ${selectedTableCapacity} guests)</strong>`;
        capacityInfo.style.display = 'block';
    }
    
    // Hide table selection, show form
    document.getElementById('reservationTableSelection').style.display = 'none';
    document.getElementById('reservationFormSection').style.display = 'block';
    
    // Setup availability checking when date/time changes
    setupAvailabilityCheck();
}

// Back to Table Selection
function backToTableSelection() {
    document.getElementById('reservationTableSelection').style.display = 'block';
    document.getElementById('reservationFormSection').style.display = 'none';
    document.getElementById('selectedTableId').value = '';
    selectedTableCapacity = null;
    
    // Reset capacity info
    const capacityInfo = document.getElementById('tableCapacityInfo');
    if (capacityInfo) {
        capacityInfo.style.display = 'none';
    }
    
    // Reset guests input
    const guestsInput = document.getElementById('reservationGuests');
    if (guestsInput) {
        guestsInput.removeAttribute('max');
        guestsInput.value = 1;
    }
    
    // Hide warnings
    const availabilityWarning = document.getElementById('availabilityWarning');
    if (availabilityWarning) {
        availabilityWarning.style.display = 'none';
    }
    
    const capacityWarning = document.getElementById('capacityWarning');
    if (capacityWarning) {
        capacityWarning.style.display = 'none';
    }
}

// Close Reservation Modal on Overlay Click
function closeReservationModalOnOverlay(event) {
    if (event.target.id === 'reservationModal') {
        document.getElementById('reservationModal').classList.remove('active');
        // Reset to table selection
        backToTableSelection();
    }
}

// Check reservation availability
async function checkReservationAvailability() {
    const tableId = document.getElementById('selectedTableId').value;
    const date = document.getElementById('reservationDate').value;
    const timeSlot = document.getElementById('reservationTimeSlot').value;
    const availabilityWarning = document.getElementById('availabilityWarning');
    
    if (!tableId || !date || !timeSlot) {
        if (availabilityWarning) {
            availabilityWarning.style.display = 'none';
        }
        return;
    }
    
    try {
        const restaurantId = getRestaurantId();
        const response = await fetch(`../check_reservation_availability.php?restaurant_id=${restaurantId}&table_id=${tableId}&reservation_date=${date}&time_slot=${timeSlot}`);
        const data = await response.json();
        
        if (data.success && !data.available) {
            // Table is already reserved
            if (availabilityWarning) {
                availabilityWarning.innerHTML = `<span style="font-weight: 600;">⚠️ Already Reserved</span> - This table is already reserved for ${date} at ${timeSlot}. Please choose a different time or table.`;
                availabilityWarning.style.display = 'block';
            }
        } else {
            // Table is available
            if (availabilityWarning) {
                availabilityWarning.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Error checking availability:', error);
    }
}

// Setup availability checking
function setupAvailabilityCheck() {
    const dateInput = document.getElementById('reservationDate');
    const timeSlotInput = document.getElementById('reservationTimeSlot');
    
    if (dateInput && timeSlotInput) {
        // Remove existing listeners to avoid duplicates
        const newDateInput = dateInput.cloneNode(true);
        const newTimeSlotInput = timeSlotInput.cloneNode(true);
        dateInput.parentNode.replaceChild(newDateInput, dateInput);
        timeSlotInput.parentNode.replaceChild(newTimeSlotInput, timeSlotInput);
        
        // Add new listeners
        newDateInput.addEventListener('change', checkReservationAvailability);
        newTimeSlotInput.addEventListener('change', checkReservationAvailability);
    }
}

// Handle Reservation Form Submission
function setupReservationForm() {
    const reservationForm = document.getElementById('reservationForm');
    if (!reservationForm) {
        console.error('Reservation form not found');
        return;
    }
    
    reservationForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const tableId = document.getElementById('selectedTableId').value;
        if (!tableId) {
            showNotification('Please select a table first', 'error');
            return;
        }
        
        const date = document.getElementById('reservationDate').value;
        const timeSlot = document.getElementById('reservationTimeSlot').value;
        const guests = parseInt(document.getElementById('reservationGuests').value) || 0;
        
        // Validate capacity
        if (selectedTableCapacity && guests > selectedTableCapacity) {
            showNotification(`This table can only accommodate ${selectedTableCapacity} guests. Please select a different table or reduce the number of guests.`, 'error');
            return;
        }
        
        if (guests <= 0) {
            showNotification('Please enter a valid number of guests', 'error');
            return;
        }
        
        // Check availability before submitting
        try {
            const restaurantId = getRestaurantId();
            const availabilityResponse = await fetch(`../check_reservation_availability.php?restaurant_id=${restaurantId}&table_id=${tableId}&reservation_date=${date}&time_slot=${timeSlot}`);
            const availabilityData = await availabilityResponse.json();
            
            if (availabilityData.success && !availabilityData.available) {
                showNotification(`This table is already reserved for ${date} at ${timeSlot}. Please choose a different time or table.`, 'error');
                return;
            }
        } catch (error) {
            console.error('Error checking availability:', error);
        }
        
        const formData = {
            table_id: parseInt(tableId),
            reservation_date: date,
            time_slot: timeSlot,
            no_of_guests: guests,
            meal_type: document.getElementById('reservationMealType').value,
            customer_name: document.getElementById('reservationName').value,
            phone: document.getElementById('reservationPhone').value,
            email: document.getElementById('reservationEmail').value,
            special_request: document.getElementById('reservationSpecialRequest').value
        };
        
        // Validation
        if (!formData.reservation_date) {
            showNotification('Please select a date', 'error');
            return;
        }
        
        if (!formData.time_slot) {
            showNotification('Please select a time slot', 'error');
            return;
        }
        
        if (!formData.customer_name.trim()) {
            showNotification('Please enter your name', 'error');
            return;
        }
        
        if (!formData.phone.trim()) {
            showNotification('Please enter your phone number', 'error');
            return;
        }
        
        // Show loading
        showNotification('Creating reservation...', 'info');
        
        try {
            const restaurantId = getRestaurantId();
            const response = await fetch(`../create_reservation.php?restaurant_id=${restaurantId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Close modal
                document.getElementById('reservationModal').classList.remove('active');
                
                // Reset form and go back to table selection
                document.getElementById('reservationForm').reset();
                backToTableSelection();
                const dateInput = document.getElementById('reservationDate');
                if (dateInput) {
                    const today = new Date().toISOString().split('T')[0];
                    dateInput.value = today;
                }
                
                showNotification('Reservation created successfully! We will confirm your reservation shortly.', 'success');
            } else {
                showNotification(data.message || 'Failed to create reservation. Please try again.', 'error');
            }
        } catch (error) {
            console.error('Error creating reservation:', error);
            showNotification('Error creating reservation. Please try again.', 'error');
        }
    });
}

// Setup top navigation links to be functional - MUST be defined before DOMContentLoaded
window.setupTopNavLinks = function() {
    // Add click handlers to top nav links
    const navLinks = document.querySelectorAll('.nav-menu .nav-link');
    if (navLinks.length > 0) {
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                if (href && href.startsWith('#')) {
                    const sectionId = href.substring(1);
                    const bottomNavItem = document.querySelector(`.bottom-nav-item[data-nav="${sectionId}"]`);
                    if (typeof scrollToSection === 'function') {
                        scrollToSection(sectionId, bottomNavItem);
                    }
                }
            });
        });
    }
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadMenus();
    loadMenuItems();
    
    // FORCE HIDE cart summary bar on load - will be shown by updateCartUI if cart has items
    const cartSummaryBar = document.getElementById('cartSummaryBar');
    const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 0), 0);
    
    // Always hide cart bar initially - FORCE HIDE if 0 items
    if (cartSummaryBar) {
        cartSummaryBar.style.display = 'none';
        cartSummaryBar.style.visibility = 'hidden';
        cartSummaryBar.classList.remove('show');
    }
    
    // Remove has-cart class if cart is empty
    if (cart.length === 0 || totalItems === 0) {
        document.body.classList.remove('has-cart');
    }
    
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
    
    // Reservation button event listener
    const reservationBtn = document.getElementById('reservationBtn');
    if (reservationBtn) {
        reservationBtn.addEventListener('click', () => {
            openReservationModal();
        });
    }
    
    // Close Reservation Modal
    const closeReservationModal = document.getElementById('closeReservationModal');
    if (closeReservationModal) {
        closeReservationModal.addEventListener('click', () => {
            document.getElementById('reservationModal').classList.remove('active');
            backToTableSelection();
        });
    }
    
    // Setup reservation form
    setupReservationForm();
    
    // Setup capacity validation
    setupCapacityValidation();
    
    // Setup top nav links
    setupTopNavLinks();
    
    // Setup profile modal close button
    const closeProfileModalBtn = document.getElementById('closeProfileModal');
    if (closeProfileModalBtn) {
        closeProfileModalBtn.addEventListener('click', closeProfileModal);
    }
    
    // Setup profile form
    setupProfileForm();
});

// Setup capacity validation on guests input
function setupCapacityValidation() {
    const guestsInput = document.getElementById('reservationGuests');
    const capacityWarning = document.getElementById('capacityWarning');
    
    if (guestsInput && capacityWarning) {
        guestsInput.addEventListener('input', function() {
            const guests = parseInt(this.value) || 0;
            
            if (selectedTableCapacity && guests > selectedTableCapacity) {
                capacityWarning.innerHTML = `<span style="font-weight: 600;">⚠️ Only ${selectedTableCapacity} seats available</span> for this table. Please reduce the number of guests.`;
                capacityWarning.style.display = 'block';
                this.setCustomValidity(`Maximum ${selectedTableCapacity} guests allowed for this table`);
            } else {
                capacityWarning.style.display = 'none';
                this.setCustomValidity('');
            }
        });
        
        guestsInput.addEventListener('change', function() {
            const guests = parseInt(this.value) || 0;
            if (selectedTableCapacity && guests > selectedTableCapacity) {
                this.value = selectedTableCapacity;
                capacityWarning.style.display = 'none';
            }
        });
    }
}

// Bottom Navigation Functions
function scrollToSection(sectionId, element) {
    // Update active state
    document.querySelectorAll('.bottom-nav-item').forEach(item => {
        item.classList.remove('active');
    });
    if (element) {
        element.classList.add('active');
    }
    
    // Update top nav active state
    document.querySelectorAll('.nav-menu .nav-link').forEach(link => {
        link.classList.remove('active');
    });
    const topNavLink = document.querySelector(`.nav-menu .nav-link[href="#${sectionId}"]`);
    if (topNavLink) {
        topNavLink.classList.add('active');
    }
    
    // Scroll to section
    const section = document.getElementById(sectionId);
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function focusSearch(element) {
    // Update active state
    document.querySelectorAll('.bottom-nav-item').forEach(item => {
        item.classList.remove('active');
    });
    if (element) {
        element.classList.add('active');
    }
    
    // Focus search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.focus();
        // Scroll to hero section if needed
        const heroSection = document.getElementById('home');
        if (heroSection) {
            heroSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

function openProfile(element, e) {
    // Prevent event propagation
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    // Update active state
    document.querySelectorAll('.bottom-nav-item').forEach(item => {
        item.classList.remove('active');
    });
    if (element) {
        element.classList.add('active');
    }
    
    // Show profile modal
    openProfileModal();
}


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
                <div class="menu-card-price">${formatCurrency(item.base_price)}</div>
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
    if (cartCount) {
        cartCount.textContent = totalItems;
    }
    
    // Update cart summary bar - ONLY show when cart has 1+ items, hide if 0 items
    const cartSummaryBar = document.getElementById('cartSummaryBar');
    const cartSummaryItems = document.getElementById('cartSummaryItems');
    const cartSummaryTotal = document.getElementById('cartSummaryTotal');
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    
    // STRICT CHECK: Only show if cart has 1 or more items
    if (cartSummaryBar) {
        // Check if cart has items (1 or more)
        const hasItems = cart.length > 0 && totalItems > 0;
        
        if (hasItems) {
            // Show yellow bar when cart has 1 or more items
            if (cartSummaryItems && cartSummaryTotal) {
                cartSummaryItems.textContent = `${totalItems} ${totalItems === 1 ? 'Item' : 'Items'}`;
                cartSummaryTotal.textContent = formatCurrencyNoDecimals(total);
            }
            cartSummaryBar.style.display = 'block';
            cartSummaryBar.style.visibility = 'visible';
            cartSummaryBar.classList.add('show');
            document.body.classList.add('has-cart');
        } else {
            // FORCE HIDE yellow bar when cart is empty (0 items)
            cartSummaryBar.style.display = 'none';
            cartSummaryBar.style.visibility = 'hidden';
            cartSummaryBar.classList.remove('show');
            document.body.classList.remove('has-cart');
        }
    }
    
    // Render cart items
    renderCartItems();
    
    // Update total
    const cartTotal = document.getElementById('cartTotal');
    if (cartTotal) {
        cartTotal.textContent = total.toFixed(2);
    }
    
    // Check if table is in URL
    const tableFromURL = getTableFromURL();
    const continueSection = document.getElementById('continueSection');
    const checkoutBtn = document.getElementById('checkoutBtn');
    
    if (tableFromURL && cart.length > 0) {
        // Show Continue button instead of Checkout
        if (continueSection) continueSection.style.display = 'block';
        if (checkoutBtn) {
            checkoutBtn.style.display = 'none';
            checkoutBtn.disabled = true;
        }
    } else {
        // Show regular checkout for website users
        if (continueSection) continueSection.style.display = 'none';
        if (checkoutBtn) {
            checkoutBtn.style.display = 'block';
            checkoutBtn.disabled = cart.length === 0;
        }
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
            <div class="cart-item-price">${formatCurrency(item.price)}</div>
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
    const bottomNav = document.querySelector('.bottom-nav');
    const cartSummaryBar = document.getElementById('cartSummaryBar');
    
    const isOpen = cartSidebar.classList.contains('open');
    const isMobile = window.innerWidth <= 768;
    
    cartSidebar.classList.toggle('open');
    cartOverlay.classList.toggle('show');
    
    // Hide/show bottom nav and cart summary bar
    if (isOpen) {
        // Cart is closing, show bottom nav only on mobile and cart summary bar (only if cart has items)
        if (bottomNav) {
            if (isMobile) {
                bottomNav.style.display = 'flex';
            } else {
                // On desktop, remove inline style to let CSS handle it (it should be hidden)
                bottomNav.style.display = '';
            }
        }
        // Only show cart summary bar if cart has 1 or more items
        const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 0), 0);
        if (cartSummaryBar) {
            if (cart.length > 0 && totalItems > 0) {
                // Show cart bar when cart has 1+ items
                cartSummaryBar.style.display = 'block';
                cartSummaryBar.style.visibility = 'visible';
                cartSummaryBar.classList.add('show');
            } else {
                // Hide cart bar when cart is empty (0 items)
                cartSummaryBar.style.display = 'none';
                cartSummaryBar.style.visibility = 'hidden';
                cartSummaryBar.classList.remove('show');
            }
        }
    } else {
        // Cart is opening, hide bottom nav and cart summary bar
        if (bottomNav) {
            bottomNav.style.display = 'none';
        }
        if (cartSummaryBar) {
            cartSummaryBar.style.display = 'none';
            cartSummaryBar.classList.remove('show');
        }
    }
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
                            <strong>Total: ${globalCurrencySymbol}<span id="summaryTotal">0.00</span></strong>
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

// Profile Modal Functions
function openProfileModal() {
    const modal = document.getElementById('profileModal');
    if (modal) {
        modal.classList.add('active');
        loadProfileData();
        loadOrderHistory();
    }
}

function closeProfileModal() {
    const modal = document.getElementById('profileModal');
    if (modal) {
        modal.classList.remove('active');
        // Reset to display mode
        cancelProfileEdit();
    }
}

function closeProfileModalOnOverlay(event) {
    if (event.target.id === 'profileModal') {
        closeProfileModal();
    }
}

// Load Profile Data from localStorage
function loadProfileData() {
    const savedCustomer = loadCustomerFromStorage();
    
    if (savedCustomer) {
        document.getElementById('profileName').textContent = savedCustomer.name || '-';
        document.getElementById('profilePhone').textContent = savedCustomer.phone || '-';
        document.getElementById('profileEmail').textContent = savedCustomer.email || '-';
        document.getElementById('profileAddress').textContent = savedCustomer.address || '-';
        
        // Populate edit form
        document.getElementById('editName').value = savedCustomer.name || '';
        document.getElementById('editPhone').value = savedCustomer.phone || '';
        document.getElementById('editEmail').value = savedCustomer.email || '';
        document.getElementById('editAddress').value = savedCustomer.address || '';
    } else {
        // No saved data
        document.getElementById('profileName').textContent = 'Not set';
        document.getElementById('profilePhone').textContent = 'Not set';
        document.getElementById('profileEmail').textContent = 'Not set';
        document.getElementById('profileAddress').textContent = 'Not set';
        
        // Show edit form by default if no data
        toggleProfileEdit();
    }
}

// Toggle Profile Edit Mode
function toggleProfileEdit() {
    const infoDisplay = document.getElementById('profileInfo');
    const editForm = document.getElementById('profileEdit');
    
    if (infoDisplay && editForm) {
        infoDisplay.style.display = infoDisplay.style.display === 'none' ? 'block' : 'none';
        editForm.style.display = editForm.style.display === 'none' ? 'block' : 'none';
    }
}

// Cancel Profile Edit
function cancelProfileEdit() {
    const infoDisplay = document.getElementById('profileInfo');
    const editForm = document.getElementById('profileEdit');
    
    if (infoDisplay && editForm) {
        infoDisplay.style.display = 'block';
        editForm.style.display = 'none';
    }
    
    // Reload data to reset form
    loadProfileData();
}

// Save Profile Changes
function setupProfileForm() {
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const profileData = {
                name: document.getElementById('editName').value.trim(),
                phone: document.getElementById('editPhone').value.trim(),
                email: document.getElementById('editEmail').value.trim(),
                address: document.getElementById('editAddress').value.trim()
            };
            
            // Validate required fields
            if (!profileData.name || !profileData.phone) {
                showNotification('Name and phone number are required', 'error');
                return;
            }
            
            // Save to localStorage
            saveCustomerToStorage(profileData);
            
            // Update display
            loadProfileData();
            
            // Switch back to display mode
            cancelProfileEdit();
            
            showNotification('Profile updated successfully!', 'success');
        });
    }
}

// Load Order History
async function loadOrderHistory() {
    const orderHistoryDiv = document.getElementById('orderHistory');
    if (!orderHistoryDiv) return;
    
    const savedCustomer = loadCustomerFromStorage();
    
    if (!savedCustomer || !savedCustomer.phone) {
        orderHistoryDiv.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-light);">Please add your phone number to view order history</div>';
        return;
    }
    
    try {
        const restaurantId = getRestaurantId();
        const response = await fetch(`api.php?action=getCustomerOrders&restaurant_id=${encodeURIComponent(restaurantId)}&phone=${encodeURIComponent(savedCustomer.phone)}`);
        const data = await response.json();
        
        if (data.error) {
            orderHistoryDiv.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-light);">No orders found</div>';
            return;
        }
        
        const orders = Array.isArray(data) ? data : (data.orders || []);
        
        if (orders.length === 0) {
            orderHistoryDiv.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-light);">No orders yet. Start ordering to see your history here!</div>';
            return;
        }
        
        // Display orders
        orderHistoryDiv.innerHTML = orders.map(order => {
            const orderDate = new Date(order.created_at).toLocaleDateString('en-IN', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const statusColor = order.order_status === 'Completed' ? '#28a745' : 
                               order.order_status === 'Pending' ? '#ffc107' : 
                               order.order_status === 'Cancelled' ? '#dc3545' : '#6c757d';
            
            return `
                <div class="order-history-item">
                    <div class="order-history-header">
                        <div>
                            <strong>Order #${order.order_number || order.id}</strong>
                            <div style="font-size: 0.85rem; color: var(--text-light); margin-top: 0.25rem;">${orderDate}</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 700; color: var(--primary-red); font-size: 1.1rem;">₹${parseFloat(order.total || 0).toFixed(2)}</div>
                            <div style="font-size: 0.85rem; color: ${statusColor}; margin-top: 0.25rem; font-weight: 600;">${order.order_status || 'Pending'}</div>
                        </div>
                    </div>
                    ${order.items && order.items.length > 0 ? `
                        <div class="order-history-items" style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--light-gray);">
                            ${order.items.slice(0, 3).map(item => `
                                <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.5rem;">
                                    <span>${item.quantity}x ${item.item_name || item.name || 'Item'}</span>
                                    <span>₹${parseFloat(item.total_price || (item.unit_price * item.quantity) || 0).toFixed(2)}</span>
                                </div>
                            `).join('')}
                            ${order.items.length > 3 ? `<div style="font-size: 0.85rem; color: var(--text-light); margin-top: 0.5rem;">+${order.items.length - 3} more items</div>` : ''}
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
        
    } catch (error) {
        console.error('Error loading order history:', error);
        orderHistoryDiv.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-light);">Error loading order history</div>';
    }
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
            
            // Ensure cart summary bar is hidden after checkout
            const cartSummaryBar = document.getElementById('cartSummaryBar');
            if (cartSummaryBar) {
                cartSummaryBar.style.display = 'none';
                cartSummaryBar.classList.remove('show');
            }
            document.body.classList.remove('has-cart');
            
            // Close cart sidebar if open
            const cartSidebar = document.getElementById('cartSidebar');
            const cartOverlay = document.getElementById('cartOverlay');
            if (cartSidebar && cartSidebar.classList.contains('open')) {
                cartSidebar.classList.remove('open');
                if (cartOverlay) {
                    cartOverlay.classList.remove('show');
                }
                // Show bottom nav again only on mobile
                const bottomNav = document.querySelector('.bottom-nav');
                if (bottomNav) {
                    const isMobile = window.innerWidth <= 768;
                    if (isMobile) {
                        bottomNav.style.display = 'flex';
                    } else {
                        // On desktop, remove inline style to let CSS handle it (it should be hidden)
                        bottomNav.style.display = '';
                    }
                }
            }
        } else {
            showNotification('Failed to place order: ' + (data.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error placing order:', error);
        showNotification('Error placing order. Please try again.', 'error');
    }
}

