// Global state
let cart = JSON.parse(localStorage.getItem('cart')) || [];
let menus = [];
let menuItems = [];
let currentFilter = {
    menuId: null,
    category: null,
    type: null
};

// Currency symbol from server-side database ONLY (set in index.php head)
// IMPORTANT: window.globalCurrencySymbol is ALWAYS set by PHP from database
// NO localStorage fallback - currency MUST come from backend database
// The currency is set in the <head> by PHP before this script loads
// So window.globalCurrencySymbol should always be available

// Verify currency is loaded from database on page load
// Wait for DOM to ensure window.globalCurrencySymbol is set by inline script
function initializeCurrency() {
    if (typeof window.globalCurrencySymbol !== 'undefined' && window.globalCurrencySymbol) {
        console.log('Currency loaded from database:', window.globalCurrencySymbol);
        // Clear localStorage and use only database value
        localStorage.setItem('system_currency', window.globalCurrencySymbol);
        // Update any initial currency displays
        updateInitialCurrencyDisplays();
    } else {
        console.error('ERROR: Currency symbol not loaded from database! window.globalCurrencySymbol:', window.globalCurrencySymbol);
    }
}

// Update initial currency displays on page load
function updateInitialCurrencyDisplays() {
    // This function will be called after formatCurrency functions are defined
    // It ensures all currency displays use the database currency
    if (!window.globalCurrencySymbol) {
        return; // Can't update without currency symbol
    }
    
    // Update cart total if it exists
    const cartTotal = document.getElementById('cartTotal');
    if (cartTotal) {
        // Extract numeric value from text (remove any existing currency symbols)
        const text = cartTotal.textContent || '0.00';
        const amount = parseFloat(text.replace(/[^\d.]/g, '')) || 0;
        cartTotal.textContent = formatCurrency(amount);
    }
    
    // Update cart summary total if it exists
    const cartSummaryTotal = document.getElementById('cartSummaryTotal');
    if (cartSummaryTotal) {
        // Extract numeric value from text (remove any existing currency symbols)
        const text = cartSummaryTotal.textContent || '0';
        const amount = parseFloat(text.replace(/[^\d.]/g, '')) || 0;
        cartSummaryTotal.textContent = formatCurrencyNoDecimals(amount);
    }
}

// Initialize currency when script loads (after inline script sets window.globalCurrencySymbol)
// We'll call this after formatCurrency functions are defined (at end of script or in DOMContentLoaded)

// Format currency helper function - uses database currency symbol ONLY
function formatCurrency(amount) {
    // ALWAYS use window.globalCurrencySymbol from database (set by PHP in head)
    // This is the ONLY source of truth - database value from backend
    // Get fresh value from window.globalCurrencySymbol every time (set by PHP)
    // Force refresh from window object to ensure we always have latest value
    const symbol = window.globalCurrencySymbol;
    if (!symbol) {
        console.error('Currency symbol not available from database! window.globalCurrencySymbol:', window.globalCurrencySymbol);
        // Try to get from database again - this should never happen if PHP loaded correctly
        return `₹${parseFloat(amount).toFixed(2)}`; // Emergency fallback only
    }
    // Always use the symbol from window.globalCurrencySymbol (database value)
    return `${symbol}${parseFloat(amount).toFixed(2)}`;
}

// Format currency without decimals (for summary bar)
function formatCurrencyNoDecimals(amount) {
    // ALWAYS use window.globalCurrencySymbol from database (set by PHP in head)
    // Force refresh from window object to ensure we always have latest value
    const symbol = window.globalCurrencySymbol;
    if (!symbol) {
        console.error('Currency symbol not available from database! window.globalCurrencySymbol:', window.globalCurrencySymbol);
        return `₹${parseFloat(amount).toFixed(0)}`; // Emergency fallback only
    }
    // Always use the symbol from window.globalCurrencySymbol (database value)
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
        const response = await fetch(`../api/get_tables.php?restaurant_id=${restaurantId}`);
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
        const response = await fetch(`../api/get_tables.php?restaurant_id=${restaurantId}`);
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
        const response = await fetch('../api/create_waiter_request.php', {
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
        const response = await fetch(`../api/get_tables.php?restaurant_id=${restaurantId}`);
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
    const timeSlotSelect = document.getElementById('reservationTimeSlot');
    const customTimeInput = document.getElementById('customTimeSlot');
    const customContainer = document.getElementById('customTimeSlotContainer');
    
    // Get time slot - either from select or custom input
    let timeSlot = '';
    if (timeSlotSelect && timeSlotSelect.value === 'custom' && customTimeInput && customTimeInput.value) {
        timeSlot = customTimeInput.value; // Custom time in 24-hour format
    } else if (timeSlotSelect && timeSlotSelect.value && timeSlotSelect.value !== 'custom') {
        timeSlot = timeSlotSelect.value;
    }
    
    const availabilityWarning = document.getElementById('availabilityWarning');
    
    if (!tableId || !date || !timeSlot) {
        if (availabilityWarning) {
            availabilityWarning.style.display = 'none';
        }
        return;
    }
    
    try {
        const restaurantId = getRestaurantId();
        const response = await fetch(`../api/check_reservation_availability.php?restaurant_id=${restaurantId}&table_id=${tableId}&reservation_date=${date}&time_slot=${timeSlot}`);
        const data = await response.json();
        
        if (data.success && !data.available) {
            // Table is already reserved
            if (availabilityWarning) {
                // Format time for display
                const [hours, minutes] = timeSlot.split(':');
                const hour = parseInt(hours);
                const displayTime = hour < 12 ? `${hour}:${minutes} AM` : hour === 12 ? `12:${minutes} PM` : `${hour - 12}:${minutes} PM`;
                availabilityWarning.innerHTML = `<span style="font-weight: 600;">⚠️ Already Reserved</span> - This table is already reserved for ${date} at ${displayTime}. Please choose a different time or table.`;
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
    const customTimeInput = document.getElementById('customTimeSlot');
    const customContainer = document.getElementById('customTimeSlotContainer');
    
    if (dateInput && timeSlotInput) {
        // Remove existing listeners to avoid duplicates
        const newDateInput = dateInput.cloneNode(true);
        const newTimeSlotInput = timeSlotInput.cloneNode(true);
        dateInput.parentNode.replaceChild(newDateInput, dateInput);
        timeSlotInput.parentNode.replaceChild(newTimeSlotInput, timeSlotInput);
        
        // Handle time slot selection change
        newTimeSlotInput.addEventListener('change', function() {
            // Clear errors
            const timeSlotError = document.getElementById('timeSlotError');
            const customTimeError = document.getElementById('customTimeSlotError');
            if (timeSlotError) {
                timeSlotError.style.display = 'none';
                timeSlotError.textContent = '';
            }
            
            if (this.value === 'custom') {
                // Show custom time input
                if (customContainer) {
                    customContainer.style.display = 'block';
                    setTimeout(() => {
                        if (customTimeInput) customTimeInput.focus();
                    }, 100);
                }
            } else {
                // Hide custom time input
                if (customContainer) customContainer.style.display = 'none';
                if (customTimeInput) {
                    customTimeInput.value = '';
                    customTimeInput.style.borderColor = '';
                }
                if (customTimeError) {
                    customTimeError.style.display = 'none';
                    customTimeError.textContent = '';
                }
                // Check availability for selected time
                checkReservationAvailability();
            }
        });
        
        // Add listeners
        newDateInput.addEventListener('change', checkReservationAvailability);
        
        // Listen to custom time input changes with validation
        if (customTimeInput) {
            const customTimeError = document.getElementById('customTimeSlotError');
            
            customTimeInput.addEventListener('change', function() {
                const timeValue = this.value;
                
                // Clear previous error styling
                this.style.borderColor = '';
                
                if (!timeValue) {
                    if (customTimeError) {
                        customTimeError.textContent = 'Please enter a custom time';
                        customTimeError.style.display = 'block';
                    }
                    this.style.borderColor = '#dc3545';
                    return;
                }
                
                // Validate time format (HH:MM)
                if (!/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(timeValue)) {
                    if (customTimeError) {
                        customTimeError.textContent = 'Invalid time format. Please enter a valid time (HH:MM)';
                        customTimeError.style.display = 'block';
                    }
                    this.style.borderColor = '#dc3545';
                    return;
                }
                
                // Clear error if valid
                if (customTimeError) {
                    customTimeError.style.display = 'none';
                    customTimeError.textContent = '';
                }
                this.style.borderColor = '';
                
                // Check availability
                checkReservationAvailability();
            });
            
            customTimeInput.addEventListener('blur', function() {
                const timeValue = this.value;
                
                if (!timeValue && newTimeSlotInput.value === 'custom') {
                    if (customTimeError) {
                        customTimeError.textContent = 'Please enter a custom time';
                        customTimeError.style.display = 'block';
                    }
                    this.style.borderColor = '#dc3545';
                } else if (timeValue && !/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(timeValue)) {
                    if (customTimeError) {
                        customTimeError.textContent = 'Invalid time format. Please enter a valid time (HH:MM)';
                        customTimeError.style.display = 'block';
                    }
                    this.style.borderColor = '#dc3545';
                }
            });
            
            customTimeInput.addEventListener('input', function() {
                // Clear error as user types
                if (this.value) {
                    if (customTimeError) {
                        customTimeError.style.display = 'none';
                        customTimeError.textContent = '';
                    }
                    this.style.borderColor = '';
                }
            });
        }
    }
}

// Helper functions for field validation
function showFieldErrorWebsite(fieldId, message) {
    const errorEl = document.getElementById(fieldId + 'Error');
    const inputEl = document.getElementById(fieldId);
    
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }
    
    if (inputEl) {
        inputEl.style.borderColor = '#dc3545';
        inputEl.style.borderWidth = '2px';
    }
}

function clearFieldErrorWebsite(fieldId) {
    const errorEl = document.getElementById(fieldId + 'Error');
    const inputEl = document.getElementById(fieldId);
    
    if (errorEl) {
        errorEl.style.display = 'none';
        errorEl.textContent = '';
    }
    
    if (inputEl) {
        inputEl.style.borderColor = '';
        inputEl.style.borderWidth = '';
    }
}

// Setup real-time validation for reservation form fields
function setupReservationFormValidation() {
    // Phone number validation - must be 10 digits
    const phoneInput = document.getElementById('reservationPhone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            const phone = e.target.value.trim();
            if (phone === '') {
                clearFieldErrorWebsite('reservationPhone');
            } else {
                const phoneDigits = phone.replace(/\D/g, '');
                if (phoneDigits.length !== 10) {
                    showFieldErrorWebsite('reservationPhone', 'Phone number must be exactly 10 digits. Please enter a valid 10-digit phone number.');
                } else {
                    clearFieldErrorWebsite('reservationPhone');
                }
            }
        });
        
        phoneInput.addEventListener('blur', function(e) {
            const phone = e.target.value.trim();
            if (phone === '') {
                showFieldErrorWebsite('reservationPhone', 'Phone number is required');
            } else {
                const phoneDigits = phone.replace(/\D/g, '');
                if (phoneDigits.length !== 10) {
                    showFieldErrorWebsite('reservationPhone', 'Phone number must be exactly 10 digits. Please enter a valid 10-digit phone number.');
                } else {
                    clearFieldErrorWebsite('reservationPhone');
                }
            }
        });
    }
    
    // Customer name validation
    const nameInput = document.getElementById('reservationName');
    if (nameInput) {
        nameInput.addEventListener('blur', function(e) {
            const name = e.target.value.trim();
            if (name === '') {
                showFieldErrorWebsite('reservationName', 'Your name is required');
            } else if (name.length < 2) {
                showFieldErrorWebsite('reservationName', 'Name must be at least 2 characters long.');
            } else if (name.length > 100) {
                showFieldErrorWebsite('reservationName', 'Name must be less than 100 characters.');
            } else {
                clearFieldErrorWebsite('reservationName');
            }
        });
        
        nameInput.addEventListener('input', function(e) {
            const name = e.target.value.trim();
            if (name.length > 100) {
                showFieldErrorWebsite('reservationName', 'Name must be less than 100 characters.');
            } else if (name.length >= 2 || name === '') {
                clearFieldErrorWebsite('reservationName');
            }
        });
    }
    
    // Email validation
    const emailInput = document.getElementById('reservationEmail');
    if (emailInput) {
        emailInput.addEventListener('blur', function(e) {
            const email = e.target.value.trim();
            if (email !== '') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    showFieldErrorWebsite('reservationEmail', 'Invalid email format. Please enter a valid email address.');
                } else {
                    clearFieldErrorWebsite('reservationEmail');
                }
            } else {
                clearFieldErrorWebsite('reservationEmail');
            }
        });
    }
    
    // Number of guests validation
    const guestsInput = document.getElementById('reservationGuests');
    if (guestsInput) {
        guestsInput.addEventListener('blur', function(e) {
            const guests = parseInt(e.target.value) || 0;
            if (guests < 1) {
                showFieldErrorWebsite('reservationGuests', 'Number of guests must be at least 1.');
            } else if (guests > 50) {
                showFieldErrorWebsite('reservationGuests', 'Number of guests cannot exceed 50.');
            } else {
                clearFieldErrorWebsite('reservationGuests');
            }
        });
        
        guestsInput.addEventListener('input', function(e) {
            const guests = parseInt(e.target.value) || 0;
            if (guests > 50) {
                showFieldErrorWebsite('reservationGuests', 'Number of guests cannot exceed 50.');
            } else if (guests >= 1 || e.target.value === '') {
                clearFieldErrorWebsite('reservationGuests');
            }
        });
    }
    
    // Special request validation
    const specialRequestInput = document.getElementById('reservationSpecialRequest');
    if (specialRequestInput) {
        specialRequestInput.addEventListener('input', function(e) {
            const text = e.target.value.trim();
            if (text.length > 500) {
                showFieldErrorWebsite('reservationSpecialRequest', 'Special request must be less than 500 characters.');
            } else {
                clearFieldErrorWebsite('reservationSpecialRequest');
            }
        });
    }
    
    // Date validation
    const dateInput = document.getElementById('reservationDate');
    if (dateInput) {
        dateInput.addEventListener('change', function(e) {
            const date = e.target.value;
            if (!date) {
                showFieldErrorWebsite('reservationDate', 'Reservation date is required');
            } else {
                const selectedDate = new Date(date);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (selectedDate < today) {
                    showFieldErrorWebsite('reservationDate', 'Reservation date cannot be in the past.');
                } else {
                    clearFieldErrorWebsite('reservationDate');
                }
            }
        });
    }
}

// Handle Reservation Form Submission
function setupReservationForm() {
    const reservationForm = document.getElementById('reservationForm');
    if (!reservationForm) {
        console.error('Reservation form not found');
        return;
    }
    
    // Setup real-time validation
    setupReservationFormValidation();
    
    reservationForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const tableId = document.getElementById('selectedTableId').value;
        if (!tableId) {
            showNotification('Please select a table first', 'error');
            return;
        }
        
        const date = document.getElementById('reservationDate').value;
        const timeSlotSelect = document.getElementById('reservationTimeSlot');
        const customTimeInput = document.getElementById('customTimeSlot');
        
        // Get time slot - either from select or custom input
        let timeSlot = '';
        const timeSlotError = document.getElementById('timeSlotError');
        const customTimeError = document.getElementById('customTimeSlotError');
        
        // Clear previous errors
        if (timeSlotError) {
            timeSlotError.style.display = 'none';
            timeSlotError.textContent = '';
        }
        if (customTimeError) {
            customTimeError.style.display = 'none';
            customTimeError.textContent = '';
        }
        
        if (timeSlotSelect && timeSlotSelect.value === 'custom') {
            if (customTimeInput && customTimeInput.value) {
                const timeValue = customTimeInput.value;
                // Validate custom time format
                if (!/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(timeValue)) {
                    if (customTimeError) {
                        customTimeError.textContent = 'Invalid time format. Please enter a valid time (HH:MM)';
                        customTimeError.style.display = 'block';
                    }
                    if (customTimeInput) {
                        customTimeInput.style.borderColor = '#dc3545';
                        customTimeInput.focus();
                    }
                    showNotification('Please enter a valid custom time (HH:MM format)', 'error');
                    return;
                }
                timeSlot = timeValue; // Custom time in 24-hour format
            } else {
                if (customTimeError) {
                    customTimeError.textContent = 'Please enter a custom time';
                    customTimeError.style.display = 'block';
                }
                if (customTimeInput) {
                    customTimeInput.style.borderColor = '#dc3545';
                    customTimeInput.focus();
                }
                showNotification('Please enter a custom time', 'error');
                return;
            }
        } else if (timeSlotSelect && timeSlotSelect.value) {
            timeSlot = timeSlotSelect.value;
        } else {
            if (timeSlotError) {
                timeSlotError.textContent = 'Please select a time slot or enter a custom time';
                timeSlotError.style.display = 'block';
            }
            showNotification('Please select a time slot or enter a custom time', 'error');
            return;
        }
        
        const guests = parseInt(document.getElementById('reservationGuests').value) || 0;
        const customerName = document.getElementById('reservationName').value.trim();
        const phone = document.getElementById('reservationPhone').value.trim();
        const email = document.getElementById('reservationEmail').value.trim();
        const specialRequest = document.getElementById('reservationSpecialRequest').value.trim();
        
        // Clear all previous errors
        clearFieldErrorWebsite('reservationDate');
        clearFieldErrorWebsite('reservationGuests');
        clearFieldErrorWebsite('reservationName');
        clearFieldErrorWebsite('reservationPhone');
        clearFieldErrorWebsite('reservationEmail');
        clearFieldErrorWebsite('reservationSpecialRequest');
        clearFieldErrorWebsite('reservationMealType');
        
        // Validate all fields
        let hasErrors = false;
        
        // Validate date
        if (!date) {
            showFieldErrorWebsite('reservationDate', 'Reservation date is required');
            hasErrors = true;
        } else {
            const selectedDate = new Date(date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            if (selectedDate < today) {
                showFieldErrorWebsite('reservationDate', 'Reservation date cannot be in the past.');
                hasErrors = true;
            }
        }
        
        // Validate time slot
        if (!timeSlot) {
            showFieldErrorWebsite('reservationTimeSlot', 'Please select a time slot or enter a custom time');
            hasErrors = true;
        }
        
        // Validate number of guests
        if (guests < 1) {
            showFieldErrorWebsite('reservationGuests', 'Number of guests must be at least 1.');
            hasErrors = true;
        } else if (guests > 50) {
            showFieldErrorWebsite('reservationGuests', 'Number of guests cannot exceed 50.');
            hasErrors = true;
        }
        
        // Validate capacity
        if (selectedTableCapacity && guests > selectedTableCapacity) {
            showFieldErrorWebsite('reservationGuests', `This table can only accommodate ${selectedTableCapacity} guests. Please select a different table or reduce the number of guests.`);
            showNotification(`This table can only accommodate ${selectedTableCapacity} guests. Please select a different table or reduce the number of guests.`, 'error');
            return;
        }
        
        // Validate customer name
        if (!customerName || customerName.trim() === '') {
            showFieldErrorWebsite('reservationName', 'Your name is required');
            hasErrors = true;
        } else if (customerName.trim().length < 2) {
            showFieldErrorWebsite('reservationName', 'Name must be at least 2 characters long.');
            hasErrors = true;
        } else if (customerName.trim().length > 100) {
            showFieldErrorWebsite('reservationName', 'Name must be less than 100 characters.');
            hasErrors = true;
        }
        
        // Validate phone number
        if (!phone || phone.trim() === '') {
            showFieldErrorWebsite('reservationPhone', 'Phone number is required');
            hasErrors = true;
        } else {
            const phoneDigits = phone.replace(/\D/g, '');
            if (phoneDigits.length !== 10) {
                showFieldErrorWebsite('reservationPhone', 'Phone number must be exactly 10 digits. Please enter a valid 10-digit phone number.');
                hasErrors = true;
            }
        }
        
        // Validate email format if provided
        if (email && email.trim() !== '') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showFieldErrorWebsite('reservationEmail', 'Invalid email format. Please enter a valid email address.');
                hasErrors = true;
            }
        }
        
        // Validate special request length
        if (specialRequest && specialRequest.trim().length > 500) {
            showFieldErrorWebsite('reservationSpecialRequest', 'Special request must be less than 500 characters.');
            hasErrors = true;
        }
        
        // If there are validation errors, stop submission
        if (hasErrors) {
            showNotification('Please fix the errors in the form', 'error');
            return;
        }
        
        // Check availability before submitting
        try {
            const restaurantId = getRestaurantId();
            const availabilityResponse = await fetch(`../api/check_reservation_availability.php?restaurant_id=${restaurantId}&table_id=${tableId}&reservation_date=${date}&time_slot=${timeSlot}`);
            const availabilityData = await availabilityResponse.json();
            
            if (availabilityData.success && !availabilityData.available) {
                // Format time for display
                const [hours, minutes] = timeSlot.split(':');
                const hour = parseInt(hours);
                const displayTime = hour < 12 ? `${hour}:${minutes} AM` : hour === 12 ? `12:${minutes} PM` : `${hour - 12}:${minutes} PM`;
                showNotification(`This table is already reserved for ${date} at ${displayTime}. Please choose a different time or table.`, 'error');
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
            showNotification('Please select a time slot or enter a custom time', 'error');
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
            const response = await fetch(`../api/create_reservation.php?restaurant_id=${restaurantId}`, {
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

// Load restaurant details and theme asynchronously
async function loadRestaurantDetails() {
    try {
        const restaurantId = getRestaurantId();
        const response = await fetch(`api.php?action=getRestaurantDetails&restaurant_id=${encodeURIComponent(restaurantId)}`);
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                // Update restaurant name
                const nameEl = document.querySelector('.nav-logo h1');
                if (nameEl && data.restaurant_name) {
                    nameEl.textContent = data.restaurant_name;
                }
                // Update logo
                if (data.restaurant_logo) {
                    const logoContainer = document.querySelector('.nav-logo');
                    if (logoContainer && !logoContainer.querySelector('img')) {
                        const img = document.createElement('img');
                        img.src = data.restaurant_logo;
                        img.alt = data.restaurant_name || 'Restaurant';
                        img.loading = 'eager';
                        img.fetchPriority = 'high';
                        img.style.cssText = 'width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-red);';
                        img.onerror = function() { this.style.display = 'none'; };
                        logoContainer.insertBefore(img, logoContainer.firstChild);
                    }
                }
                // Update currency
                if (data.currency_symbol) {
                    window.globalCurrencySymbol = data.currency_symbol;
                    localStorage.setItem('system_currency', data.currency_symbol);
                    initializeCurrency();
                }
                // Update theme colors
                if (data.theme) {
                    const root = document.documentElement;
                    if (data.theme.primary_red) root.style.setProperty('--primary-red', data.theme.primary_red);
                    if (data.theme.dark_red) root.style.setProperty('--dark-red', data.theme.dark_red);
                    if (data.theme.primary_yellow) root.style.setProperty('--primary-yellow', data.theme.primary_yellow);
                }
            }
        }
    } catch (error) {
        console.error('Error loading restaurant details:', error);
    }
}

// Initialize - Optimized for mobile performance
document.addEventListener('DOMContentLoaded', () => {
    // Initialize currency first (ensures formatCurrency functions can use it)
    initializeCurrency();
    
    // Load restaurant details asynchronously
    loadRestaurantDetails();
    
    // Defer API calls to improve initial render on mobile
    // Use requestIdleCallback if available, otherwise setTimeout
    if ('requestIdleCallback' in window) {
        requestIdleCallback(() => {
            loadMenus();
            loadMenuItems();
        }, { timeout: 2000 });
    } else {
        setTimeout(() => {
            loadMenus();
            loadMenuItems();
        }, 100);
    }
    
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
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearch);
        searchInput.addEventListener('focus', () => {
            // Show suggestions if we have them
            if (searchSuggestions.length > 0) {
                const suggestionsDiv = document.getElementById('searchSuggestions');
                if (suggestionsDiv) suggestionsDiv.style.display = 'block';
            }
        });
        searchInput.addEventListener('blur', (e) => {
            // Delay hiding to allow clicks on suggestions
            setTimeout(() => {
                const suggestionsDiv = document.getElementById('searchSuggestions');
                if (suggestionsDiv && !suggestionsDiv.matches(':hover')) {
                    suggestionsDiv.style.display = 'none';
                }
            }, 200);
        });
        searchInput.addEventListener('keydown', (e) => {
            const suggestionsDiv = document.getElementById('searchSuggestions');
            if (!suggestionsDiv || suggestionsDiv.style.display === 'none') return;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedSuggestionIndex = Math.min(selectedSuggestionIndex + 1, searchSuggestions.length - 1);
                renderSearchSuggestions();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedSuggestionIndex = Math.max(selectedSuggestionIndex - 1, -1);
                renderSearchSuggestions();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedSuggestionIndex >= 0 && selectedSuggestionIndex < searchSuggestions.length) {
                    selectSuggestion(selectedSuggestionIndex);
                }
            } else if (e.key === 'Escape') {
                suggestionsDiv.style.display = 'none';
                selectedSuggestionIndex = -1;
            }
        });
    }
    
    // Add click handler to search button
    const searchBtn = document.querySelector('.search-btn');
    if (searchBtn) {
        searchBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            await performSearch();
        });
    }
    
    // Also handle Enter key in search input to trigger search
    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
    }
    
    document.getElementById('typeFilter').addEventListener('change', handleFilter);
    document.getElementById('categoryFilter').addEventListener('change', handleFilter);
    
    // Mobile veg toggle
    const mobileVegToggle = document.getElementById('mobileVegToggle');
    if (mobileVegToggle) {
        mobileVegToggle.addEventListener('change', function() {
            if (this.checked) {
                currentFilter.type = 'Veg';
            } else {
                currentFilter.type = null;
            }
            loadMenuItems(currentFilter);
        });
    }
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
    
    // Setup item modal close button
    const closeItemModalBtn = document.getElementById('closeItemModal');
    if (closeItemModalBtn) {
        closeItemModalBtn.addEventListener('click', closeItemModal);
    }
    
    // Close item modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const itemModal = document.getElementById('itemModal');
            if (itemModal && itemModal.classList.contains('active')) {
                closeItemModal();
            }
        }
    });
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
    // Primary source: PHP inlined value (database verified)
    if (window.websiteRestaurantId) {
        return window.websiteRestaurantId;
    }
    
    // Secondary source: data attribute on body (set by PHP)
    const body = document.body;
    if (body && body.dataset && body.dataset.restaurantId) {
        window.websiteRestaurantId = body.dataset.restaurantId;
        return window.websiteRestaurantId;
    }
    
    // Fallback: meta tag (also set by PHP)
    const metaRestaurant = document.querySelector('meta[name="restaurant-id"]');
    if (metaRestaurant && metaRestaurant.content) {
        window.websiteRestaurantId = metaRestaurant.content;
        return window.websiteRestaurantId;
    }
    
    // Final fallback: query parameter (may not exist for clean URLs)
    const urlParams = new URLSearchParams(window.location.search);
    const restaurantIdFromUrl = urlParams.get('restaurant_id');
    if (restaurantIdFromUrl) {
        window.websiteRestaurantId = restaurantIdFromUrl;
        return restaurantIdFromUrl;
    }
    
    // Absolute fallback - should rarely be used
    return 'RES001';
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
        
        // Handle both array response and wrapped response
        if (Array.isArray(data)) {
            menus = data;
        } else if (data.success && Array.isArray(data.data)) {
            menus = data.data;
        } else {
            menus = [];
        }
        
        console.log('Loaded menus for mobile:', menus.length, menus);
        renderMenuTabs();
        
        // Load categories
        const catResponse = await fetch(`api.php?action=getCategories&restaurant_id=${encodeURIComponent(restaurantId)}`);
        const catData = await catResponse.json();
        const categories = Array.isArray(catData) ? catData : [];
        populateCategoryFilter(categories);
        
        // Render mobile menu categories (Breakfast, Snacks, etc.) with images
        if (menus.length > 0) {
            renderMobileMenuCategories(menus);
        } else {
            console.warn('No menus found to display');
        }
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
        const categoryName = typeof category === 'string' ? category : category.name;
        option.value = categoryName;
        option.textContent = categoryName;
        categoryFilter.appendChild(option);
    });
}

// Render mobile menu categories (Breakfast, Snacks, etc.) with images
function renderMobileMenuCategories(menus) {
    const mobileCategoryScroll = document.getElementById('mobileCategoryScroll');
    if (!mobileCategoryScroll) {
        console.error('mobileCategoryScroll element not found');
        return;
    }
    
    // Clear existing
    mobileCategoryScroll.innerHTML = '';
    
    // Add "All" button first
    const allItem = document.createElement('div');
    allItem.className = 'mobile-category-item active';
    allItem.dataset.menu = 'all';
    allItem.onclick = () => selectMobileMenu(null, 'All');
    
    const allImageDiv = document.createElement('div');
    allImageDiv.className = 'mobile-category-image';
    const allIcon = document.createElement('span');
    allIcon.className = 'material-symbols-rounded';
    allIcon.textContent = 'restaurant_menu';
    allImageDiv.appendChild(allIcon);
    
    const allLabel = document.createElement('span');
    allLabel.className = 'mobile-category-label';
    allLabel.textContent = 'All';
    
    allItem.appendChild(allImageDiv);
    allItem.appendChild(allLabel);
    mobileCategoryScroll.appendChild(allItem);
    
    // Add each menu category
    menus.forEach(menu => {
        const menuName = menu.menu_name || 'Menu';
        const menuImage = menu.menu_image || null;
        
        const categoryItem = document.createElement('div');
        categoryItem.className = 'mobile-category-item';
        categoryItem.dataset.menu = menu.id;
        categoryItem.onclick = () => selectMobileMenu(menu.id, menuName);
        
        const imageDiv = document.createElement('div');
        imageDiv.className = 'mobile-category-image';
        
        if (menuImage) {
            const img = document.createElement('img');
            // Handle database-stored images
            if (menuImage.startsWith('db:')) {
                img.src = `api/image.php?type=menu&id=${menu.id}`;
            } else if (menuImage.startsWith('http')) {
                img.src = menuImage;
            } else {
                img.src = `api/image.php?path=${encodeURIComponent(menuImage)}`;
            }
            img.alt = menuName;
            img.onerror = function() {
                this.style.display = 'none';
                const icon = document.createElement('span');
                icon.className = 'material-symbols-rounded';
                icon.textContent = 'restaurant';
                imageDiv.appendChild(icon);
            };
            imageDiv.appendChild(img);
        } else {
            const icon = document.createElement('span');
            icon.className = 'material-symbols-rounded';
            icon.textContent = 'restaurant';
            imageDiv.appendChild(icon);
        }
        
        const label = document.createElement('span');
        label.className = 'mobile-category-label';
        // Truncate long names but keep it readable
        const displayName = menuName.length > 15 ? menuName.substring(0, 13) + '...' : menuName;
        label.textContent = displayName;
        
        categoryItem.appendChild(imageDiv);
        categoryItem.appendChild(label);
        mobileCategoryScroll.appendChild(categoryItem);
    });
}

// Render mobile item categories with images (for when a menu is selected)
function renderMobileCategories(categories) {
    const mobileCategoryScroll = document.getElementById('mobileCategoryScroll');
    if (!mobileCategoryScroll) return;
    
    // Clear existing
    mobileCategoryScroll.innerHTML = '';
    
    // Add "All" button
    const allItem = document.createElement('div');
    allItem.className = 'mobile-category-item active';
    allItem.dataset.category = 'all';
    allItem.onclick = () => selectMobileCategory('all');
    
    const allImageDiv = document.createElement('div');
    allImageDiv.className = 'mobile-category-image';
    const allIcon = document.createElement('span');
    allIcon.className = 'material-symbols-rounded';
    allIcon.textContent = 'restaurant_menu';
    allImageDiv.appendChild(allIcon);
    
    const allLabel = document.createElement('span');
    allLabel.className = 'mobile-category-label';
    allLabel.textContent = 'All';
    
    allItem.appendChild(allImageDiv);
    allItem.appendChild(allLabel);
    mobileCategoryScroll.appendChild(allItem);
    
    // Add each category
    categories.forEach(category => {
        const categoryName = typeof category === 'string' ? category : category.name;
        const categoryImage = category.image || null;
        
        const categoryItem = document.createElement('div');
        categoryItem.className = 'mobile-category-item';
        categoryItem.dataset.category = categoryName;
        categoryItem.onclick = () => selectMobileCategory(categoryName);
        
        const imageDiv = document.createElement('div');
        imageDiv.className = 'mobile-category-image';
        
        if (categoryImage) {
            const img = document.createElement('img');
            img.src = `api/image.php?path=${encodeURIComponent(categoryImage)}`;
            img.alt = categoryName;
            img.onerror = function() {
                this.style.display = 'none';
                const icon = document.createElement('span');
                icon.className = 'material-symbols-rounded';
                icon.textContent = 'restaurant';
                imageDiv.appendChild(icon);
            };
            imageDiv.appendChild(img);
        } else {
            const icon = document.createElement('span');
            icon.className = 'material-symbols-rounded';
            icon.textContent = 'restaurant';
            imageDiv.appendChild(icon);
        }
        
        const label = document.createElement('span');
        label.className = 'mobile-category-label';
        label.textContent = categoryName.length > 12 ? categoryName.substring(0, 10) + '...' : categoryName;
        
        categoryItem.appendChild(imageDiv);
        categoryItem.appendChild(label);
        mobileCategoryScroll.appendChild(categoryItem);
    });
}

// Select mobile menu (Breakfast, Snacks, etc.)
function selectMobileMenu(menuId, menuName) {
    // Update active state
    document.querySelectorAll('.mobile-category-item').forEach(item => {
        item.classList.remove('active');
    });
    const selectedItem = document.querySelector(`.mobile-category-item[data-menu="${menuId}"]`);
    if (selectedItem) {
        selectedItem.classList.add('active');
    }
    
    // Update header title
    const title = document.getElementById('mobileCategoryTitle');
    if (title) {
        title.textContent = menuName;
    }
    
    // Show mobile header
    const header = document.getElementById('mobileCategoryHeader');
    if (header) {
        header.style.display = 'flex';
    }
    
    // Filter by menu
    filterByMenu(menuId);
    
    // Load categories for this menu and show them (only if menuId is not null)
    if (menuId) {
        loadCategoriesForMenu(menuId);
    } else {
        // If "All" is selected, show menu categories again
        renderMobileMenuCategories(menus);
    }
    
    // Scroll to menu section
    const menuSection = document.getElementById('menu');
    if (menuSection) {
        menuSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Load categories for a specific menu
async function loadCategoriesForMenu(menuId) {
    try {
        const restaurantId = getRestaurantId();
        let url = `api.php?action=getCategories&restaurant_id=${encodeURIComponent(restaurantId)}`;
        if (menuId) {
            url += `&menu_id=${encodeURIComponent(menuId)}`;
        }
        const response = await fetch(url);
        const catData = await response.json();
        const categories = Array.isArray(catData) ? catData : [];
        renderMobileCategories(categories);
    } catch (error) {
        console.error('Error loading categories for menu:', error);
    }
}

// Scroll mobile categories
function scrollMobileCategories(direction) {
    const scrollContainer = document.getElementById('mobileCategoryScroll');
    if (!scrollContainer) return;
    
    const scrollAmount = 200;
    const currentScroll = scrollContainer.scrollLeft;
    const newScroll = direction === 'left' 
        ? currentScroll - scrollAmount 
        : currentScroll + scrollAmount;
    
    scrollContainer.scrollTo({
        left: newScroll,
        behavior: 'smooth'
    });
}

// Select mobile category (item category within a menu)
function selectMobileCategory(categoryName) {
    // Update active state
    document.querySelectorAll('.mobile-category-item').forEach(item => {
        item.classList.remove('active');
    });
    const selectedItem = document.querySelector(`.mobile-category-item[data-category="${categoryName}"]`);
    if (selectedItem) {
        selectedItem.classList.add('active');
    }
    
    // Update header title
    const title = document.getElementById('mobileCategoryTitle');
    if (title) {
        title.textContent = categoryName === 'all' ? 'Menu' : categoryName;
    }
    
    // Show mobile header
    const header = document.getElementById('mobileCategoryHeader');
    if (header) {
        header.style.display = 'flex';
    }
    
    // Filter menu items
    currentFilter.category = categoryName === 'all' ? null : categoryName;
    loadMenuItems(currentFilter);
    
    // Scroll to menu section
    const menuSection = document.getElementById('menu');
    if (menuSection) {
        menuSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Go back to categories view
function goBackToCategories() {
    // If we're viewing item categories, go back to menu categories
    const currentCategoryItem = document.querySelector('.mobile-category-item.active[data-category]');
    if (currentCategoryItem) {
        // We're in item categories, go back to menu categories
        renderMobileMenuCategories(menus);
        const header = document.getElementById('mobileCategoryHeader');
        if (header) {
            header.style.display = 'none';
        }
        filterByMenu(null);
        return;
    }
    
    // Otherwise, just hide header and reset
    const header = document.getElementById('mobileCategoryHeader');
    if (header) {
        header.style.display = 'none';
    }
    
    // Reset to "All"
    const allItem = document.querySelector('.mobile-category-item[data-menu="all"]');
    if (allItem) {
        allItem.click();
    }
}

// Focus search
function focusSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.focus();
    }
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
            ${imageUrl ? `<img src="${imageUrl}" alt="${escapeHtml(item.item_name_en)}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">` : '🍽️'}
        </div>
        <div class="menu-card-content">
            <div class="menu-card-header">
                <div class="menu-card-name">${escapeHtml(item.item_name_en)}</div>
                <div class="menu-type-badge">${typeBadge}</div>
            </div>
            <div class="menu-card-description">${escapeHtml(item.item_description_en || 'Delicious food item')}</div>
            <div class="menu-card-footer">
                <div class="menu-card-price">${formatCurrency(item.base_price)}</div>
                <button class="add-to-cart-btn" data-item-id="${item.id}">
                    <span class="material-symbols-rounded">add_shopping_cart</span>
                    Add
                </button>
            </div>
        </div>
    `;
    
    // Make the entire card clickable (except the add button)
    card.style.cursor = 'pointer';
    card.addEventListener('click', function(e) {
        // Don't open modal if clicking the add button
        if (e.target.closest('.add-to-cart-btn')) {
            return;
        }
        console.log('Card clicked, opening modal for item:', item.id);
        if (typeof openItemModal === 'function') {
            openItemModal(item.id);
        } else {
            console.error('openItemModal function not found!');
        }
    });
    
    // Add to cart button click handler
    const addBtn = card.querySelector('.add-to-cart-btn');
    if (addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent card click
            addToCart(item.id, item.item_name_en, item.base_price, item.item_image || '');
        });
    }
    
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
        cartTotal.textContent = formatCurrency(total);
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
            ${imageUrl ? `<img src="${imageUrl}" alt="${item.name}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">` : '🍽️'}
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
let searchSuggestions = [];
let selectedSuggestionIndex = -1;

// Perform search function
async function performSearch() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) {
        console.error('Search input not found');
        return;
    }
    
    const searchTerm = searchInput.value.trim();
    const suggestionsDiv = document.getElementById('searchSuggestions');
    
    // Hide suggestions dropdown
    if (suggestionsDiv) {
        suggestionsDiv.style.display = 'none';
    }
    
    // If search is empty, show all items
    if (searchTerm.length === 0) {
        loadMenuItems(currentFilter);
        selectedSuggestionIndex = -1;
        return;
    }
    
    try {
        const restaurantId = getRestaurantId();
        if (!restaurantId) {
            console.error('Restaurant ID not found');
            return;
        }
        
        const response = await fetch(`api.php?action=searchItems&q=${encodeURIComponent(searchTerm)}&restaurant_id=${encodeURIComponent(restaurantId)}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        // Check for error
        if (data.error) {
            console.error('Search Error:', data.error);
            menuItems = [];
            searchSuggestions = [];
            renderMenuItems();
            return;
        }
        
        // Get search results
        menuItems = Array.isArray(data) ? data : (data.items || []);
        searchSuggestions = menuItems.slice(0, 5);
        
        // Render the search results
        renderMenuItems();
        
        // Scroll to menu section to show results
        setTimeout(() => {
            const menuSection = document.getElementById('menu');
            if (menuSection) {
                menuSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 100);
        
    } catch (error) {
        console.error('Search error:', error);
        menuItems = [];
        searchSuggestions = [];
        renderMenuItems();
    }
}

function handleSearch(e) {
    const searchTerm = e.target.value.trim();
    const suggestionsDiv = document.getElementById('searchSuggestions');
    
    clearTimeout(searchTimeout);
    
    // Hide suggestions if search is empty
    if (searchTerm.length === 0) {
        if (suggestionsDiv) suggestionsDiv.style.display = 'none';
        loadMenuItems(currentFilter);
        selectedSuggestionIndex = -1;
        return;
    }
    
    if (searchTerm.length < 2) {
        if (suggestionsDiv) suggestionsDiv.style.display = 'none';
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
                searchSuggestions = [];
            } else {
                menuItems = Array.isArray(data) ? data : [];
                // Show top 5 results in suggestions
                searchSuggestions = menuItems.slice(0, 5);
            }
            
            // Show suggestions dropdown
            renderSearchSuggestions();
            renderMenuItems();
        } catch (error) {
            console.error('Search error:', error);
            menuItems = [];
            searchSuggestions = [];
            if (suggestionsDiv) suggestionsDiv.style.display = 'none';
            renderMenuItems();
        }
    }, 300);
}

// Render search suggestions dropdown
function renderSearchSuggestions() {
    const suggestionsDiv = document.getElementById('searchSuggestions');
    if (!suggestionsDiv) return;
    
    if (searchSuggestions.length === 0) {
        suggestionsDiv.innerHTML = `
            <div class="search-suggestions-empty">
                <span class="material-symbols-rounded">search_off</span>
                <div>No results found</div>
            </div>
        `;
        suggestionsDiv.style.display = 'block';
        return;
    }
    
    suggestionsDiv.innerHTML = searchSuggestions.map((item, index) => {
        const itemName = item.item_name_en || item.item_name || 'Unknown Item';
        const category = item.item_category || '';
        const price = item.base_price || 0;
        const imageUrl = item.item_image ? `image.php?path=${encodeURIComponent(item.item_image)}` : '';
        
        // Build image HTML
        let imageHtml = '';
        if (imageUrl) {
            imageHtml = `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(itemName)}" class="search-suggestions-item-image" loading="lazy" onerror="this.onerror=null; this.style.display='none'; const fallback = this.nextElementSibling; if(fallback) fallback.style.display='flex';">`;
            // Add fallback icon div that shows if image fails
            imageHtml += `<div class="search-suggestions-item-image" style="display: none; align-items: center; justify-content: center; color: var(--text-light); background: var(--light-gray);">
                <span class="material-symbols-rounded">restaurant_menu</span>
            </div>`;
        } else {
            // Show icon if no image
            imageHtml = `<div class="search-suggestions-item-image" style="display: flex; align-items: center; justify-content: center; color: var(--text-light); background: var(--light-gray);">
                <span class="material-symbols-rounded">restaurant_menu</span>
            </div>`;
        }
        
        const escapedImage = escapeHtml(item.item_image || '');
        const escapedName = escapeHtml(itemName);
        return `
            <div class="search-suggestions-item ${index === selectedSuggestionIndex ? 'selected' : ''}" 
                 data-index="${index}" 
                 onmouseenter="highlightSuggestion(${index})">
                ${imageHtml}
                <div class="search-suggestions-item-info" style="flex: 1;">
                    <div class="search-suggestions-item-name">${escapedName}</div>
                    ${category ? `<div class="search-suggestions-item-category">${escapeHtml(category)}</div>` : ''}
                    <div class="search-suggestions-item-price">${formatCurrency(price)}</div>
                </div>
                <button class="add-to-cart-btn" style="padding: 0.5rem 1rem; font-size: 0.85rem; margin-left: 0.5rem; flex-shrink: 0;" 
                        onclick="event.stopPropagation(); addToCartFromSearch(${item.id}, '${escapedName.replace(/'/g, "\\'")}', ${price}, '${escapedImage.replace(/'/g, "\\'")}')">
                    <span class="material-symbols-rounded" style="font-size: 1rem;">add_shopping_cart</span>
                    Add
                </button>
            </div>
        `;
    }).join('');
    
    suggestionsDiv.style.display = 'block';
    
    // Prevent blur when clicking on suggestions
    suggestionsDiv.addEventListener('mousedown', (e) => {
        e.preventDefault();
    });
}

// Select a suggestion
function selectSuggestion(index) {
    if (index >= 0 && index < searchSuggestions.length) {
        const item = searchSuggestions[index];
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = item.item_name_en || item.item_name || '';
        }
        
        // Hide suggestions
        const suggestionsDiv = document.getElementById('searchSuggestions');
        if (suggestionsDiv) suggestionsDiv.style.display = 'none';
        
        // Scroll to menu section and highlight the item
        const menuSection = document.getElementById('menu');
        if (menuSection) {
            menuSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // Trigger search to show full results
        if (searchInput) {
            searchInput.dispatchEvent(new Event('input'));
        }
    }
}

// Highlight suggestion on hover
function highlightSuggestion(index) {
    selectedSuggestionIndex = index;
    renderSearchSuggestions();
}

// Escape HTML helper
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
                            <strong>Total: <span id="summaryTotal">${formatCurrency(0)}</span></strong>
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
                <span>${formatCurrency(item.price * item.quantity)}</span>
            </div>
        `).join('');
    }
    
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const summaryTotal = document.getElementById('summaryTotal');
    if (summaryTotal) {
        summaryTotal.textContent = formatCurrency(total);
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
                            <div style="font-weight: 700; color: var(--primary-red); font-size: 1.1rem;">${formatCurrency(order.total || 0)}</div>
                            <div style="font-size: 0.85rem; color: ${statusColor}; margin-top: 0.25rem; font-weight: 600;">${order.order_status || 'Pending'}</div>
                        </div>
                    </div>
                    ${order.items && order.items.length > 0 ? `
                        <div class="order-history-items" style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--light-gray);">
                            ${order.items.slice(0, 3).map(item => `
                                <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.5rem;">
                                    <span>${item.quantity}x ${item.item_name || item.name || 'Item'}</span>
                                    <span>${formatCurrency(item.total_price || (item.unit_price * item.quantity) || 0)}</span>
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

// Item Details Modal Functions - Make sure it's globally accessible
window.openItemModal = function(itemId) {
    console.log('openItemModal called with itemId:', itemId);
    console.log('Available menuItems:', menuItems.length);
    
    const item = menuItems.find(i => i.id === itemId);
    if (!item) {
        console.error('Item not found:', itemId, 'Available items:', menuItems.map(i => i.id));
        return;
    }
    
    console.log('Item found:', item);
    
    const modal = document.getElementById('itemModal');
    const modalBody = document.getElementById('itemModalBody');
    
    if (!modal || !modalBody) {
        console.error('Item modal elements not found. Modal:', modal, 'ModalBody:', modalBody);
        return;
    }
    
    console.log('Opening modal...');
    
    const typeBadge = item.item_type === 'Veg' ? '🌱 Veg' : 
                      item.item_type === 'Non Veg' ? '🍖 Non Veg' :
                      item.item_type === 'Egg' ? '🥚 Egg' :
                      item.item_type === 'Drink' ? '🥤 Drink' : '';
    
    const imageUrl = item.item_image ? `image.php?path=${encodeURIComponent(item.item_image)}` : '';
    const existingCartItem = cart.find(c => c.id === item.id);
    const currentQuantity = existingCartItem ? existingCartItem.quantity : 1;
    itemModalQuantity = currentQuantity;
    currentItemModalItem = item;
    
    modalBody.innerHTML = `
        <div class="item-modal-image">
            ${imageUrl ? `<img src="${imageUrl}" alt="${escapeHtml(item.item_name_en)}" loading="eager" fetchpriority="high" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">` : ''}
            <div class="item-modal-image-placeholder" style="display: ${imageUrl ? 'none' : 'flex'};">
                <span class="material-symbols-rounded" style="font-size: 5rem; opacity: 0.3;">restaurant_menu</span>
            </div>
        </div>
        <div class="item-modal-info">
            <div class="item-modal-header">
                <h2 class="item-modal-name">${escapeHtml(item.item_name_en)}</h2>
                <div class="item-modal-type-badge">${typeBadge}</div>
            </div>
            <div class="item-modal-price">${formatCurrency(item.base_price)}</div>
            <div class="item-modal-description">
                <h3>Description</h3>
                <p>${escapeHtml(item.item_description_en || 'Delicious food item. Order now to enjoy this amazing dish!')}</p>
            </div>
            <div class="item-modal-actions">
                <div class="item-quantity-controls">
                    <button class="quantity-btn" id="itemModalQuantityDecrease">
                        <span class="material-symbols-rounded">remove</span>
                    </button>
                    <input type="number" id="itemModalQuantity" class="quantity-input" value="${currentQuantity}" min="1">
                    <button class="quantity-btn" id="itemModalQuantityIncrease">
                        <span class="material-symbols-rounded">add</span>
                    </button>
                </div>
                <button class="add-to-cart-modal-btn" id="addToCartFromModalBtn" data-item-id="${item.id}">
                    <span class="material-symbols-rounded">add_shopping_cart</span>
                    <span>Add to Cart</span>
                    <span class="item-modal-total-price">${formatCurrency(item.base_price * currentQuantity)}</span>
                </button>
            </div>
        </div>
    `;
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Setup event listeners for modal controls
    setTimeout(() => {
        const decreaseBtn = document.getElementById('itemModalQuantityDecrease');
        const increaseBtn = document.getElementById('itemModalQuantityIncrease');
        const quantityInput = document.getElementById('itemModalQuantity');
        const addToCartBtn = document.getElementById('addToCartFromModalBtn');
        
        if (decreaseBtn) {
            decreaseBtn.onclick = () => updateItemModalQuantity(-1);
        }
        
        if (increaseBtn) {
            increaseBtn.onclick = () => updateItemModalQuantity(1);
        }
        
        if (quantityInput) {
            quantityInput.onchange = (e) => updateItemModalQuantityInput(e.target.value);
        }
        
        if (addToCartBtn) {
            addToCartBtn.onclick = () => {
                addToCartFromModal(item.id, item.item_name_en, item.base_price, item.item_image || '');
            };
        }
    }, 10);
}

window.closeItemModal = function() {
    const modal = document.getElementById('itemModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

window.closeItemModalOnOverlay = function(event) {
    if (event.target.id === 'itemModal') {
        closeItemModal();
    }
}

let itemModalQuantity = 1;
let currentItemModalItem = null;

function updateItemModalQuantity(change) {
    const quantityInput = document.getElementById('itemModalQuantity');
    if (!quantityInput) return;
    
    const currentValue = parseInt(quantityInput.value) || 1;
    const newValue = Math.max(1, currentValue + change);
    quantityInput.value = newValue;
    itemModalQuantity = newValue;
    
    updateItemModalTotalPrice();
}

function updateItemModalQuantityInput(value) {
    const newValue = Math.max(1, parseInt(value) || 1);
    itemModalQuantity = newValue;
    const quantityInput = document.getElementById('itemModalQuantity');
    if (quantityInput) {
        quantityInput.value = newValue;
    }
    updateItemModalTotalPrice();
}

function updateItemModalTotalPrice() {
    const totalPriceElement = document.querySelector('.item-modal-total-price');
    if (!totalPriceElement) return;
    
    // Find the current item from the modal
    const modalBody = document.getElementById('itemModalBody');
    if (!modalBody) return;
    
    const priceElement = modalBody.querySelector('.item-modal-price');
    if (!priceElement) return;
    
    // Extract price from the price element text
    const priceText = priceElement.textContent;
    const price = parseFloat(priceText.replace(/[^\d.]/g, '')) || 0;
    const quantity = itemModalQuantity || 1;
    const total = price * quantity;
    
    totalPriceElement.textContent = formatCurrency(total);
}

function addToCartFromModal(itemId, itemName, itemPrice, itemImage) {
    const quantityInput = document.getElementById('itemModalQuantity');
    const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;
    
    const existingItem = cart.find(item => item.id === itemId);
    
    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        cart.push({
            id: itemId,
            name: itemName,
            price: parseFloat(itemPrice),
            image: itemImage,
            quantity: quantity
        });
    }
    
    updateCartStorage();
    updateCartUI();
    showCartNotification();
    closeItemModal();
    showNotification(`${quantity}x ${itemName} added to cart!`, 'success');
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
        const restaurantId = getRestaurantId();
        const response = await fetch(`../api/process_website_order.php?restaurant_id=${encodeURIComponent(restaurantId)}`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'include', // Include cookies for session
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

