<?php
session_start();
if (!isset($_SESSION['staff_id']) || !isset($_SESSION['restaurant_id']) || $_SESSION['role'] !== 'Waiter') {
    header('Location: ../admin/login.php');
    exit();
}

$restaurant_id = $_SESSION['restaurant_id'];
$currency_symbol = $_SESSION['currency_symbol'] ?? null;

if (!$currency_symbol) {
    try {
        if (file_exists(__DIR__ . '/../db_connection.php')) {
            require_once __DIR__ . '/../db_connection.php';
            
            if (isset($pdo) && $pdo instanceof PDO) {
                $conn = $pdo;
            } elseif (function_exists('getConnection')) {
                $conn = getConnection();
            } else {
                $host = 'localhost';
                $dbname = 'restro2';
                $username = 'root';
                $password = '';
                $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            }
            
            $currencyStmt = $conn->prepare("SELECT currency_symbol FROM users WHERE restaurant_id = ? LIMIT 1");
            $currencyStmt->execute([$restaurant_id]);
            $currencyRow = $currencyStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($currencyRow && !empty($currencyRow['currency_symbol'])) {
                $currency_symbol = $currencyRow['currency_symbol'];
                $_SESSION['currency_symbol'] = $currency_symbol;
            }
        }
    } catch (Exception $e) {
        // Ignore and fallback to default below
    }
}

if (!$currency_symbol) {
    $currency_symbol = '₹';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiter Dashboard - <?php echo htmlspecialchars($_SESSION['restaurant_name'] ?? 'Restaurant'); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.globalCurrencySymbol = <?php echo json_encode($currency_symbol, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const waiterCurrencySymbol = window.globalCurrencySymbol || '₹';
    </script>
    <script src="../assets/js/script.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; background: #f3f4f6; }
        .main-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .waiter-header { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; padding: 20px; margin-bottom: 24px; border-radius: 12px; }
        .page-tabs { display: flex; gap: 10px; margin-bottom: 24px; border-bottom: 2px solid #e5e7eb; overflow-x: auto; }
        .tab { padding: 12px 24px; cursor: pointer; font-weight: 600; color: #6b7280; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; }
        .tab.active { color: #3b82f6; border-bottom-color: #3b82f6; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .request-card { background: white; border: 2px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .request-card.pending { border-left: 4px solid #f59e0b; }
        .request-card.attended { border-left: 4px solid #3b82f6; }
        .order-card { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 12px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        
        /* POS Responsive */
        .pos-content { display: grid; grid-template-columns: 1fr 400px; gap: 24px; margin-top: 24px; }
        .pos-filters { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .pos-menu-items { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; max-height: 70vh; overflow-y: auto; padding: 8px; }
        .pos-cart-section { background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; position: sticky; top: 20px; height: fit-content; max-height: 85vh; overflow-y: auto; }
        .pos-menu-item { background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; cursor: pointer; transition: all 0.2s; text-align: center; }
        .pos-menu-item:hover { transform: translateY(-4px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .pos-menu-item .item-image { width: 100%; height: 120px; background: #f9fafb; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; overflow: hidden; }
        .pos-menu-item .item-image img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
        .pos-menu-item .item-name { font-weight: 600; margin-bottom: 4px; color: #111827; font-size: 0.95rem; }
        .pos-menu-item .item-category { font-size: 0.875rem; color: #6b7280; margin-bottom: 8px; }
        .pos-menu-item .item-price { font-weight: 700; color: #3b82f6; font-size: 1.1rem; background: white; padding: 4px 8px; border-radius: 6px; display: inline-block; }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .main-container { padding: 12px; }
            .waiter-header { padding: 16px; }
            .waiter-header h1 { font-size: 1.25rem !important; }
            .page-tabs { gap: 8px; margin-bottom: 16px; }
            .tab { padding: 10px 16px; font-size: 0.9rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .page-header h2 { font-size: 1.25rem; margin: 0; }
            .request-card, .order-card { padding: 16px; margin-bottom: 12px; }
            .order-card > div { flex-direction: column !important; }
            .order-card .btn { width: 100%; margin-top: 12px; }
            
            /* POS Mobile Styles - Items first, then cart below */
            .pos-content { grid-template-columns: 1fr; gap: 20px; margin-top: 20px; }
            .pos-menu-section { order: -1; }
            .pos-cart-section { order: 1; position: static; max-height: none; border-radius: 12px; padding: 16px; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 2px solid #3b82f6; }
            .pos-menu-items { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; max-height: none; padding: 8px; }
            .pos-menu-item { padding: 12px; border: 1px solid #e5e7eb; }
            .pos-menu-item:active { transform: scale(0.98); }
            .pos-menu-item .item-image { height: 100px; margin-bottom: 8px; }
            .pos-menu-item .item-name { font-size: 0.85rem; line-height: 1.3; }
            .pos-menu-item .item-category { display: none; }
            .pos-menu-item .item-price { font-size: 1rem; }
            #posSearchBar { width: 100%; padding: 12px 16px; font-size: 1rem; margin-bottom: 16px; }
            .pos-filters { flex-direction: column; gap: 10px; }
            .pos-filters select { width: 100%; padding: 12px; font-size: 1rem; border: 1px solid #d1d5db; background: white; }
            .btn { width: 100%; padding: 12px; font-size: 1rem; margin-bottom: 8px; }
            .pos-cart-summary { margin-top: 16px; padding-top: 16px; border-top: 2px solid #e5e7eb; }
            .pos-cart-items { max-height: 300px; overflow-y: auto; }
            
            /* Notification mobile styles */
            .waiter-toast-notification { 
                top: 10px !important;
                right: 10px !important;
                left: 10px !important;
                max-width: none !important;
                font-size: 0.9rem !important;
                padding: 12px 16px !important;
            }
        }
        
        @media (max-width: 480px) {
            .main-container { padding: 8px; }
            .waiter-header { padding: 12px; }
            .tab { padding: 8px 12px; font-size: 0.85rem; }
            .request-card, .order-card { padding: 12px; }
            .pos-menu-items { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .pos-menu-item { padding: 10px; }
            .pos-menu-item .item-image { height: 80px; }
            .pos-menu-item .item-name { font-size: 0.8rem; }
            .pos-menu-item .item-category { font-size: 0.75rem; }
            .pos-menu-item .item-price { font-size: 0.9rem; }
            .pos-cart-section { padding: 12px; }
            .pos-filters select { padding: 10px; font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <header class="waiter-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="margin: 0; font-size: 1.75rem;">🍽️ Waiter Dashboard</h1>
                    <p style="margin: 8px 0 0 0; opacity: 0.9;"><?php echo htmlspecialchars($_SESSION['restaurant_name'] ?? 'Restaurant'); ?></p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.9rem; opacity: 0.9;">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Waiter'); ?></div>
                    <a href="../controllers/staff_logout.php" style="color: white; text-decoration: none; font-weight: 600; margin-top: 8px; display: inline-block; padding: 8px 16px; background: rgba(255,255,255,0.2); border-radius: 8px; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">Logout</a>
                </div>
            </div>
        </header>

        <div class="main-content">
            <div class="page-tabs">
                <div class="tab active" onclick="switchTab('requests')">Waiter Requests</div>
                <div class="tab" onclick="switchTab('orders')">Active Orders</div>
                <div class="tab" onclick="switchTab('pos')">POS</div>
            </div>

            <!-- Waiter Requests Tab -->
            <div id="requestsTab" class="tab-content active">
                <div class="page-header">
                    <h2>Waiter Requests</h2>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span id="lastRefresh" style="color: #6b7280; font-size: 0.875rem;">Auto-refreshing every 5 seconds...</span>
                        <button class="btn btn-primary" onclick="loadWaiterRequests()">
                            <span class="material-symbols-rounded" style="vertical-align: middle;">refresh</span> Refresh
                        </button>
                    </div>
                </div>
                <div id="waiterRequestsList"></div>
            </div>

            <!-- Active Orders Tab -->
            <div id="ordersTab" class="tab-content">
                <div class="page-header">
                    <h2>Active Orders</h2>
                    <button class="btn btn-primary" onclick="loadActiveOrders()">
                        <span class="material-symbols-rounded" style="vertical-align: middle;">refresh</span> Refresh
                    </button>
                </div>
                <div id="activeOrdersList"></div>
            </div>

            <!-- POS Tab -->
            <div id="posTab" class="tab-content">
                <div class="page-header">
                    <h2>Point of Sale</h2>
                    <p>Process orders and manage transactions</p>
                </div>
                <div class="pos-content">
                    <!-- Left Side - Menu Items -->
                    <div class="pos-menu-section">
                        <!-- Search Bar -->
                        <div style="margin-bottom: 16px;">
                            <input type="text" id="posSearchBar" placeholder="🔍 Search menu items..." style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 1rem; transition: all 0.2s;" oninput="filterPOSItems()" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'" onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none'">
                        </div>
                        <div class="pos-filters" style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;">
                            <select id="posMenuFilter" class="filter-select" style="flex: 1; min-width: 150px; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px;">
                                <option value="">All Menus</option>
                            </select>
                            <select id="posCategoryFilter" class="filter-select" style="flex: 1; min-width: 150px; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px;">
                                <option value="">All Categories</option>
                            </select>
                            <select id="posTypeFilter" class="filter-select" style="flex: 1; min-width: 120px; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px;">
                                <option value="">All Types</option>
                                <option value="Veg">Veg</option>
                                <option value="Non Veg">Non Veg</option>
                                <option value="Egg">Egg</option>
                                <option value="Drink">Drink</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="pos-menu-items" id="posMenuItems" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; max-height: 70vh; overflow-y: auto; padding: 8px;">
                            <div class="loading">Loading menu items...</div>
                        </div>
                    </div>

                    <!-- Right Side - Cart -->
                    <div class="pos-cart-section" style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; position: sticky; top: 20px; height: fit-content; max-height: 85vh; overflow-y: auto;">
                        <h3 style="margin-top: 0; margin-bottom: 16px;">Order Cart</h3>
                        
                        <div class="pos-table-select" style="margin-bottom: 16px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Select Table:</label>
                            <select id="selectPosTable" class="filter-select" style="width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px;">
                                <option value="">Takeaway</option>
                            </select>
                        </div>

                        <div class="pos-cart-items" id="posCartItems" style="margin-bottom: 16px; padding: 16px; background: #f9fafb; border-radius: 8px; min-height: 200px;">
                            <div class="empty-cart" style="text-align: center; color: #6b7280; padding: 40px;">
                                <span class="material-symbols-rounded" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 8px;">shopping_cart</span>
                                <p style="margin: 0;">Cart is empty</p>
                                <p class="empty-subtext" style="margin: 4px 0 0 0; font-size: 0.875rem;">Add items from the menu</p>
                            </div>
                        </div>

                        <div class="pos-cart-summary" style="margin-bottom: 16px; padding-top: 16px; border-top: 2px solid #e5e7eb;">
                            <div class="cart-summary-row" style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>Subtotal:</span>
                                <span id="cartSubtotal"><?php echo htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'); ?>0.00</span>
                            </div>
                            <div class="cart-summary-row" style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>CGST (2.5%):</span>
                                <span id="cartCGST"><?php echo htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'); ?>0.00</span>
                            </div>
                            <div class="cart-summary-row" style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>SGST (2.5%):</span>
                                <span id="cartSGST"><?php echo htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'); ?>0.00</span>
                            </div>
                            <div class="cart-summary-row total" style="display: flex; justify-content: space-between; font-weight: 700; font-size: 1.1rem; padding-top: 8px; border-top: 2px solid #e5e7eb;">
                                <span>Total:</span>
                                <span id="cartTotal"><?php echo htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'); ?>0.00</span>
                            </div>
                        </div>

                        <div style="display: flex; gap: 12px;">
                            <button class="btn btn-secondary" id="clearCartBtn" style="flex: 1; background: #6b7280;">
                                <span class="material-symbols-rounded" style="vertical-align: middle;">delete</span> Clear
                            </button>
                            <button class="btn btn-secondary" id="holdOrderBtn" style="flex: 1; background: #f59e0b;">
                                <span class="material-symbols-rounded" style="vertical-align: middle;">pause</span> Hold
                            </button>
                            <button class="btn btn-primary" id="processPaymentBtn" style="flex: 1;">
                                <span class="material-symbols-rounded" style="vertical-align: middle;">payment</span> Process
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const waiterRestaurantId = <?php echo json_encode($restaurant_id); ?>;
        const waiterRestaurantIdQuery = waiterRestaurantId ? encodeURIComponent(waiterRestaurantId) : '';
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById(tab + 'Tab').classList.add('active');
            if (tab === 'requests') loadWaiterRequests();
            else if (tab === 'orders') loadActiveOrders();
            else if (tab === 'pos') {
                // Initialize POS cart if not exists
                if (typeof window.posCart === 'undefined') {
                    window.posCart = [];
                }
                
                // Wait for script.js to load
                setTimeout(() => {
                    // Load POS data
                    if (typeof loadPOSMenuItems === 'function') {
                        loadPOSMenuItems();
                    } else {
                        console.warn('loadPOSMenuItems function not found, trying alternative...');
                        loadPOSDataForWaiter();
                    }
                    if (typeof loadTablesForPOS === 'function') {
                        loadTablesForPOS();
                    } else {
                        loadTablesForWaiterPOS();
                    }
                    if (typeof loadMenusForPOSFilters === 'function') {
                        loadMenusForPOSFilters();
                    } else {
                        loadMenusForWaiterPOS();
                    }
                    if (typeof loadCategoriesForPOSFilters === 'function') {
                        loadCategoriesForPOSFilters();
                    } else {
                        loadCategoriesForWaiterPOS();
                    }
                }, 100);
            }
        }

        async function loadWaiterRequests() {
            try {
                const response = await fetch(`../api/get_waiter_requests.php?restaurant_id=${waiterRestaurantIdQuery}`);
                const result = await response.json();
                
                const list = document.getElementById('waiterRequestsList');
                const lastRefresh = document.getElementById('lastRefresh');
                
                // Update last refresh time
                if (lastRefresh) {
                    lastRefresh.textContent = 'Last updated: ' + new Date().toLocaleTimeString();
                }
                
                if (result.success && result.requests && result.requests.length > 0) {
                    list.innerHTML = result.requests.map(req => {
                        const statusClass = req.status.toLowerCase();
                        
                        return `
                            <div class="request-card ${statusClass}">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                    <div>
                                        <strong style="font-size: 1.1rem;">Table ${req.table_number}</strong>
                                        <div style="color: #6b7280; font-size: 0.875rem;">${req.area_name || ''}</div>
                                    </div>
                                    <span class="badge ${statusClass === 'pending' ? 'badge-warning' : 'badge-success'}">${req.status}</span>
                                </div>
                                <div style="color: #6b7280; margin-bottom: 12px;">
                                    <strong>Request:</strong> ${req.request_type || 'General'}
                                </div>
                                ${req.notes ? `<div style="color: #6b7280; margin-bottom: 12px;"><strong>Notes:</strong> ${req.notes}</div>` : ''}
                                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 16px;">
                                    ${new Date(req.created_at).toLocaleString()}
                                </div>
                                ${req.status === 'Pending' ? `
                                    <button class="btn btn-success" onclick="markAttended(${req.id})">Mark as Attended</button>
                                ` : ''}
                            </div>
                        `;
                    }).join('');
                } else {
                    list.innerHTML = '<div style="text-align: center; padding: 40px; color: #6b7280;">No waiter requests at the moment</div>';
                }
            } catch (error) {
                console.error('Error loading requests:', error);
                document.getElementById('waiterRequestsList').innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;">Error loading requests</div>';
            }
        }

        async function loadActiveOrders() {
            return new Promise(async (resolve, reject) => {
                try {
                    // Fetch orders with status Ready (orders that are ready to be served)
                    const list = document.getElementById('activeOrdersList');
                    if (list) {
                        list.innerHTML = '<div class="loading">Refreshing orders...</div>';
                    }
                    const response = await fetch(`../api/get_orders.php?restaurant_id=${waiterRestaurantIdQuery}&status=Ready`, { cache: 'no-store' });
                    const result = await response.json();
                
                
                
                if (result.success && result.orders && result.orders.length > 0) {
                    list.innerHTML = result.orders.map(order => {
                        const tableInfo = order.table_name || (order.table_number ? `Table ${order.table_number}${order.area_name ? ` (${order.area_name})` : ''}` : 'Takeaway');
                        const items = order.items || [];
                        
                        return `
                        <div class="order-card" style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                                <div style="flex: 1; min-width: 200px;">
                                    <strong style="font-size: 1.1rem; color: #111827; display: block; margin-bottom: 8px;">Order #${order.order_number || order.id}</strong>
                                    <div style="color: #dc2626; font-size: 1rem; font-weight: 600; margin-bottom: 8px;">${tableInfo}</div>
                                    <div style="color: #6b7280; font-size: 0.875rem;">
                                        ${new Date(order.created_at).toLocaleString()}
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <span class="badge badge-info" style="background: #3b82f6; color: white; padding: 8px 16px; border-radius: 8px; font-weight: 600; display: inline-block; margin-bottom: 8px;">${waiterCurrencySymbol}${parseFloat(order.total || order.total_amount || 0).toFixed(2)}</span>
                                </div>
                            </div>
                            
                            <div style="margin: 16px 0; padding: 16px; background: #f9fafb; border-radius: 8px;">
                                <h4 style="margin: 0 0 12px 0; color: #111827; font-size: 0.95rem; font-weight: 600;">Order Items (${items.length})</h4>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    ${items.map(item => `
                                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: white; border-radius: 6px;">
                                            <div style="flex: 1;">
                                                <div style="font-weight: 600; color: #111827; margin-bottom: 4px;">${item.item_name || 'Item'}</div>
                                                ${item.notes ? `<div style="color: #f59e0b; font-size: 0.85rem; font-style: italic;">Note: ${item.notes}</div>` : ''}
                                            </div>
                                            <div style="text-align: right; margin-left: 16px;">
                                                <div style="color: #6b7280; font-size: 0.875rem;">Qty: ${item.quantity || 1}</div>
                                                <div style="color: #111827; font-weight: 600;">${waiterCurrencySymbol}${parseFloat(item.total_price || item.unit_price || 0).toFixed(2)}</div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 16px; border-top: 2px solid #e5e7eb; flex-wrap: wrap; gap: 12px;">
                                <div style="color: #6b7280; font-size: 0.875rem; flex: 1;">
                                    <strong>Subtotal:</strong> ${waiterCurrencySymbol}${parseFloat(order.subtotal || 0).toFixed(2)} | 
                                    <strong>Tax:</strong> ${waiterCurrencySymbol}${parseFloat(order.tax || 0).toFixed(2)}
                                </div>
                                <button class="btn btn-success" onclick="markOrderServed(${order.id})" style="padding: 12px 24px; font-size: 1rem; white-space: nowrap;">
                                    <span class="material-symbols-rounded" style="vertical-align: middle; font-size: 1.2rem;">check_circle</span>
                                    Mark as Served
                                </button>
                            </div>
                        </div>
                    `;
                    }).join('');
                } else {
                    list.innerHTML = '<div style="text-align: center; padding: 40px; color: #6b7280;">No active orders ready to serve</div>';
                }
                    resolve();
                } catch (error) {
                    console.error('Error loading orders:', error);
                    document.getElementById('activeOrdersList').innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;">Error loading orders</div>';
                    reject(error);
                }
            });
        }
        
        async function markOrderServed(orderId) {
            showConfirmModal('Are you sure you want to mark this order as served?', async () => {
                try {
                    const response = await fetch('../api/update_order_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `orderId=${orderId}&status=Served`
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        showNotification('Order marked as served successfully!', 'success');
                        loadActiveOrders(); // Refresh immediately after update
                    } else {
                        showNotification(result.message || 'Failed to update order', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('Error updating order status', 'error');
                }
            });
        }
        
        // Make markOrderServed globally available
        window.markOrderServed = markOrderServed;

        async function markAttended(requestId) {
            try {
                const response = await fetch('../controllers/waiter_request_operations.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=mark_attended&requestId=${requestId}`
                });
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Request marked as attended!', 'success');
                    loadWaiterRequests(); // Refresh immediately after update
                } else {
                    showNotification(result.message || 'Failed to update request', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error updating status', 'error');
            }
        }
        
        // Make markAttended globally available
        window.markAttended = markAttended;

        // Auto-refresh with intelligent polling (faster when orders exist)
        let refreshInterval = null;
        let lastOrdersCount = 0;
        
        function startAutoRefresh() {
            if (refreshInterval) clearInterval(refreshInterval);
            
            const activeTab = document.querySelector('.tab.active');
            const tabId = activeTab ? activeTab.textContent.trim() : '';
            
            // Check if we have orders/requests - refresh more frequently if yes
            const checkInterval = () => {
                const ordersTab = document.getElementById('ordersTab');
                const requestsTab = document.getElementById('requestsTab');
                
                if (ordersTab && ordersTab.classList.contains('active')) {
                    const prevOrderCount = document.querySelectorAll('.order-card').length;
                    loadActiveOrders().then(() => {
                        // Check if order count changed after loading
                        setTimeout(() => {
                            const currentOrders = document.querySelectorAll('.order-card').length;
                            if (currentOrders !== prevOrderCount && prevOrderCount > 0) {
                                // Flash notification if new orders
                                showNotification('New order arrived!', 'success');
                            }
                            lastOrdersCount = currentOrders;
                        }, 100);
                    }).catch(() => {
                        // Handle error silently
                    });
                } else if (requestsTab && requestsTab.classList.contains('active')) {
                    loadWaiterRequests();
                }
            };
            
            // Refresh every 3 seconds (more frequent for real-time feel)
            refreshInterval = setInterval(checkInterval, 3000);
            // Also check immediately
            checkInterval();
        }
        
        // Start auto-refresh on page load
        startAutoRefresh();
        
        // Restart auto-refresh when tab changes
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                setTimeout(() => startAutoRefresh(), 100);
            });
        });
        
        // Pause auto-refresh when page is hidden, resume when visible
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                    refreshInterval = null;
                }
            } else {
                startAutoRefresh();
            }
        });
        
        // Enhanced Notification system
        function showNotification(message, type = 'info', duration = 4000) {
            const notification = document.createElement('div');
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };
            const colors = {
                success: '#10b981',
                error: '#ef4444',
                warning: '#f59e0b',
                info: '#3b82f6'
            };
            
            notification.className = 'waiter-toast-notification';
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${colors[type] || colors.info};
                color: white;
                padding: 16px 24px;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.2);
                z-index: 10000;
                animation: slideInRight 0.3s ease-out;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 12px;
                max-width: 400px;
                word-wrap: break-word;
            `;
            notification.innerHTML = `
                <span style="font-size: 1.5rem;">${icons[type] || icons.info}</span>
                <span style="flex: 1;">${message}</span>
                <button onclick="this.parentElement.remove()" style="background: rgba(255,255,255,0.2); border: none; color: white; border-radius: 4px; padding: 4px 8px; cursor: pointer; font-size: 1.2rem; line-height: 1;">×</button>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, duration);
        }
        
        // Confirmation Modal
        function showConfirmModal(message, onConfirm, onCancel = null) {
            // Remove existing modal if any
            const existing = document.getElementById('waiterConfirmModal');
            if (existing) existing.remove();
            
            const modal = document.createElement('div');
            modal.id = 'waiterConfirmModal';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10001;
                animation: fadeIn 0.2s ease-out;
            `;
            
            modal.innerHTML = `
                <div style="background: white; border-radius: 16px; padding: 24px; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUp 0.3s ease-out;">
                    <div style="text-align: center; margin-bottom: 24px;">
                        <div style="font-size: 3rem; margin-bottom: 16px;">🤔</div>
                        <h3 style="margin: 0 0 8px 0; color: #111827; font-size: 1.25rem;">Confirm Action</h3>
                        <p style="margin: 0; color: #6b7280; font-size: 1rem;">${message}</p>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button id="confirmCancelBtn" style="flex: 1; padding: 12px; border: 2px solid #e5e7eb; background: white; border-radius: 8px; font-weight: 600; color: #6b7280; cursor: pointer; transition: all 0.2s;">
                            Cancel
                        </button>
                        <button id="confirmOkBtn" style="flex: 1; padding: 12px; border: none; background: #3b82f6; color: white; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                            Confirm
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            const cancelBtn = document.getElementById('confirmCancelBtn');
            const okBtn = document.getElementById('confirmOkBtn');
            
            const closeModal = () => {
                modal.style.animation = 'fadeOut 0.2s ease-out';
                setTimeout(() => modal.remove(), 200);
            };
            
            cancelBtn.onclick = () => {
                closeModal();
                if (onCancel) onCancel();
            };
            
            okBtn.onclick = () => {
                closeModal();
                if (onConfirm) onConfirm();
            };
            
            // Close on background click
            modal.onclick = (e) => {
                if (e.target === modal) {
                    closeModal();
                    if (onCancel) onCancel();
                }
            };
            
            // Close on Escape key
            const escapeHandler = (e) => {
                if (e.key === 'Escape') {
                    closeModal();
                    if (onCancel) onCancel();
                    document.removeEventListener('keydown', escapeHandler);
                }
            };
            document.addEventListener('keydown', escapeHandler);
        }
        
        // Make functions globally available
        window.showNotification = showNotification;
        window.showConfirmModal = showConfirmModal;
        
        // Add animation styles
        if (!document.getElementById('waiter-notification-styles')) {
            const style = document.createElement('style');
            style.id = 'waiter-notification-styles';
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                @keyframes fadeOut {
                    from { opacity: 1; }
                    to { opacity: 0; }
                }
                @keyframes slideUp {
                    from { transform: translateY(20px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        }
        
        // Store all menu items for filtering
        let allPOSItems = [];
        
        // POS Functions for Waiter Dashboard (if script.js functions not available)
        async function loadPOSDataForWaiter() {
            try {
                const response = await fetch(`../api/get_menu_items.php?restaurant_id=${waiterRestaurantIdQuery}`);
                const result = await response.json();
                
                if (result.success && result.data && result.data.length > 0) {
                    // Store all items for filtering
                    allPOSItems = result.data;
                    displayPOSItems(allPOSItems);
                } else {
                    document.getElementById('posMenuItems').innerHTML = '<div class="loading">No menu items found</div>';
                }
            } catch (error) {
                console.error('Error loading POS menu items:', error);
                document.getElementById('posMenuItems').innerHTML = '<div class="loading">Error loading menu items</div>';
            }
        }
        
        // Display POS items
        function displayPOSItems(items) {
            const container = document.getElementById('posMenuItems');
            if (!container) return;
            
            if (items.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 40px; color: #6b7280;">No items match your search</div>';
                return;
            }
            
            container.innerHTML = items.map(item => {
                const itemName = (item.item_name_en || item.item_name || '').replace(/'/g, "\\'");
                return `
                    <div class="pos-menu-item" onclick="addToWaiterPOSCart(${item.id}, '${itemName}', ${item.base_price || item.price}, '${item.item_image || ''}')" style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; cursor: pointer; transition: all 0.2s; text-align: center;">
                        <div class="item-image" style="width: 100%; height: 120px; background: #f9fafb; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; overflow: hidden;">
                            ${item.item_image ? `<img src="../api/image.php?path=${encodeURIComponent(item.item_image)}" alt="${itemName}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">` : '<span class="material-symbols-rounded" style="font-size: 3rem; color: #9ca3af;">restaurant</span>'}
                        </div>
                        <div class="item-name" style="font-weight: 600; margin-bottom: 4px; color: #111827;">${item.item_name_en || item.item_name}</div>
                        <div class="item-category" style="font-size: 0.875rem; color: #6b7280; margin-bottom: 8px;">${item.item_category || ''}</div>
                        <div class="item-price" style="font-weight: 700; color: #3b82f6; font-size: 1.1rem;">${waiterCurrencySymbol}${parseFloat(item.base_price || item.price || 0).toFixed(2)}</div>
                    </div>
                `;
            }).join('');
        }
        
        // Filter POS items by search and filters
        function filterPOSItems() {
            const searchTerm = document.getElementById('posSearchBar')?.value.toLowerCase() || '';
            const menuFilter = document.getElementById('posMenuFilter')?.value || '';
            const categoryFilter = document.getElementById('posCategoryFilter')?.value || '';
            const typeFilter = document.getElementById('posTypeFilter')?.value || '';
            
            let filtered = allPOSItems.filter(item => {
                const matchesSearch = !searchTerm || 
                    (item.item_name_en || item.item_name || '').toLowerCase().includes(searchTerm) ||
                    (item.item_category || '').toLowerCase().includes(searchTerm);
                
                const matchesMenu = !menuFilter || item.menu_id == menuFilter;
                const matchesCategory = !categoryFilter || (item.item_category || '').toLowerCase() === categoryFilter.toLowerCase();
                const matchesType = !typeFilter || (item.item_type || '').toLowerCase() === typeFilter.toLowerCase();
                
                return matchesSearch && matchesMenu && matchesCategory && matchesType;
            });
            
            displayPOSItems(filtered);
        }
        
        // Make filter function globally available
        window.filterPOSItems = filterPOSItems;
        
        // Also filter when dropdowns change
        document.addEventListener('DOMContentLoaded', function() {
            const menuFilter = document.getElementById('posMenuFilter');
            const categoryFilter = document.getElementById('posCategoryFilter');
            const typeFilter = document.getElementById('posTypeFilter');
            
            if (menuFilter) menuFilter.addEventListener('change', filterPOSItems);
            if (categoryFilter) categoryFilter.addEventListener('change', filterPOSItems);
            if (typeFilter) typeFilter.addEventListener('change', filterPOSItems);
        });
        
        async function loadTablesForWaiterPOS() {
            try {
                const response = await fetch(`../api/get_tables.php?restaurant_id=${waiterRestaurantIdQuery}`);
                const result = await response.json();
                
                const select = document.getElementById('selectPosTable');
                if (result.success && result.data && result.data.length > 0) {
                    select.innerHTML = '<option value="">Takeaway</option>' + 
                        result.data.map(table => 
                            `<option value="${table.id}">${table.table_number} - ${table.area_name || ''}</option>`
                        ).join('');
                }
            } catch (error) {
                console.error('Error loading tables:', error);
            }
        }
        
        async function loadMenusForWaiterPOS() {
            try {
                const response = await fetch(`../api/get_menus.php?restaurant_id=${waiterRestaurantIdQuery}`);
                const result = await response.json();
                
                const select = document.getElementById('posMenuFilter');
                if (result.success && result.data && result.data.length > 0) {
                    select.innerHTML = '<option value="">All Menus</option>' + 
                        result.data.map(menu => 
                            `<option value="${menu.id}">${menu.menu_name}</option>`
                        ).join('');
                }
            } catch (error) {
                console.error('Error loading menus:', error);
            }
        }
        
        async function loadCategoriesForWaiterPOS() {
            try {
                const response = await fetch(`../api/get_menu_items.php?restaurant_id=${waiterRestaurantIdQuery}`);
                const result = await response.json();
                
                const select = document.getElementById('posCategoryFilter');
                if (result.success && result.categories && result.categories.length > 0) {
                    select.innerHTML = '<option value="">All Categories</option>' + 
                        result.categories.map(category => 
                            `<option value="${category}">${category}</option>`
                        ).join('');
                }
            } catch (error) {
                console.error('Error loading categories:', error);
            }
        }
        
        // Cart functions for waiter POS
        if (typeof window.posCart === 'undefined') {
            window.posCart = [];
        }
        
        function addToWaiterPOSCart(itemId, itemName, price, image) {
            const existingItem = window.posCart.find(item => item.id === itemId);
            
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                window.posCart.push({
                    id: itemId,
                    name: itemName,
                    price: price,
                    image: image,
                    quantity: 1
                });
            }
            
            updateWaiterPOSCart();
        }
        
        function updateWaiterPOSCart() {
            const container = document.getElementById('posCartItems');
            
            if (window.posCart.length === 0) {
                container.innerHTML = `
                    <div class="empty-cart" style="text-align: center; color: #6b7280; padding: 40px;">
                        <span class="material-symbols-rounded" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 8px;">shopping_cart</span>
                        <p style="margin: 0;">Cart is empty</p>
                        <p class="empty-subtext" style="margin: 4px 0 0 0; font-size: 0.875rem;">Add items from the menu</p>
                    </div>
                `;
            } else {
                container.innerHTML = window.posCart.map(item => `
                    <div class="cart-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: white; border-radius: 8px; margin-bottom: 8px; border: 1px solid #e5e7eb;">
                        <div class="cart-item-info" style="flex: 1;">
                            <div class="cart-item-name" style="font-weight: 600; margin-bottom: 4px;">${item.name}</div>
                            <div class="cart-item-price" style="font-size: 0.875rem; color: #6b7280;">${waiterCurrencySymbol}${parseFloat(item.price).toFixed(2)} each</div>
                        </div>
                        <div class="cart-item-controls" style="display: flex; align-items: center; gap: 12px; margin: 0 16px;">
                            <button onclick="updateWaiterPOSCartQty(${item.id}, -1)" style="padding: 6px 12px; border: 1px solid #e5e7eb; background: white; border-radius: 6px; cursor: pointer; font-weight: 600;">-</button>
                            <span style="min-width: 30px; text-align: center; font-weight: 600;">${item.quantity}</span>
                            <button onclick="updateWaiterPOSCartQty(${item.id}, 1)" style="padding: 6px 12px; border: 1px solid #e5e7eb; background: white; border-radius: 6px; cursor: pointer; font-weight: 600;">+</button>
                        </div>
                        <div class="cart-item-total" style="font-weight: 700; color: #111827; min-width: 80px; text-align: right;">${waiterCurrencySymbol}${(parseFloat(item.price) * item.quantity).toFixed(2)}</div>
                    </div>
                `).join('');
            }
            
            updateWaiterPOSCartSummary();
        }
        
        function updateWaiterPOSCartQty(itemId, change) {
            const item = window.posCart.find(item => item.id === itemId);
            
            if (item) {
                item.quantity += change;
                
                if (item.quantity <= 0) {
                    window.posCart = window.posCart.filter(cartItem => cartItem.id !== itemId);
                }
                
                updateWaiterPOSCart();
            }
        }
        
        function updateWaiterPOSCartSummary() {
            const subtotal = window.posCart.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
            const cgst = subtotal * 0.025; // 2.5%
            const sgst = subtotal * 0.025; // 2.5%
            const tax = cgst + sgst; // GST total 5%
            const total = subtotal + tax;
            
            document.getElementById('cartSubtotal').textContent = `${waiterCurrencySymbol}${subtotal.toFixed(2)}`;
            document.getElementById('cartCGST').textContent = `${waiterCurrencySymbol}${cgst.toFixed(2)}`;
            document.getElementById('cartSGST').textContent = `${waiterCurrencySymbol}${sgst.toFixed(2)}`;
            document.getElementById('cartTotal').textContent = `${waiterCurrencySymbol}${total.toFixed(2)}`;
        }
        
        // Make functions globally available
        window.addToWaiterPOSCart = addToWaiterPOSCart;
        window.updateWaiterPOSCartQty = updateWaiterPOSCartQty;
        
        // Clear cart button
        const clearCartBtn = document.getElementById('clearCartBtn');
        if (clearCartBtn) {
            clearCartBtn.addEventListener('click', () => {
                if (window.posCart.length > 0) {
                    showConfirmModal('Are you sure you want to clear the cart?', () => {
                        window.posCart = [];
                        updateWaiterPOSCart();
                        showNotification('Cart cleared', 'info');
                    });
                }
            });
        }
        
        // Hold order button
        const holdOrderBtn = document.getElementById('holdOrderBtn');
        if (holdOrderBtn) {
            holdOrderBtn.addEventListener('click', async () => {
                if (window.posCart.length === 0) {
                    showNotification('Cart is empty', 'warning');
                    return;
                }
                
                const subtotal = window.posCart.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
                const tax = subtotal * 0.05;
                const total = subtotal + tax;
                const selectedTable = document.getElementById('selectPosTable').value;
                
                const formData = new URLSearchParams();
                formData.append('action', 'hold_order');
                formData.append('tableId', selectedTable || '');
                formData.append('cartItems', JSON.stringify(window.posCart));
                formData.append('subtotal', subtotal.toFixed(2));
                formData.append('tax', tax.toFixed(2));
                formData.append('total', total.toFixed(2));
                
                try {
                    const response = await fetch('../controllers/pos_operations.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        showNotification(`${result.message} - Order #${result.order_number}`, 'success');
                        window.posCart = [];
                        updateWaiterPOSCart();
                        document.getElementById('selectPosTable').value = '';
                    } else {
                        showNotification(result.message || 'Failed to hold order', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('Network error. Please try again.', 'error');
                }
            });
        }
        
        // Process payment button
        const processPaymentBtn = document.getElementById('processPaymentBtn');
        if (processPaymentBtn) {
            processPaymentBtn.addEventListener('click', async () => {
                if (window.posCart.length === 0) {
                    showNotification('Cart is empty', 'warning');
                    return;
                }
                
                // Show payment method selection modal
                const paymentMethods = [
                    { id: 'Cash', label: 'Cash', icon: '💵' },
                    { id: 'Card', label: 'Card', icon: '💳' },
                    { id: 'UPI', label: 'UPI', icon: '📱' },
                    { id: 'Online', label: 'Online', icon: '🌐' }
                ];
                
                const modal = document.createElement('div');
                modal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10001;
                    animation: fadeIn 0.2s ease-out;
                `;
                
                modal.innerHTML = `
                    <div style="background: white; border-radius: 16px; padding: 24px; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUp 0.3s ease-out;">
                        <h3 style="margin: 0 0 20px 0; color: #111827; font-size: 1.25rem; text-align: center;">Select Payment Method</h3>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                            ${paymentMethods.map(method => `
                                <button class="payment-method-btn" data-method="${method.id}" style="padding: 16px; border: 2px solid #e5e7eb; background: white; border-radius: 12px; cursor: pointer; transition: all 0.2s; text-align: center;" onmouseover="this.style.borderColor='#3b82f6'; this.style.transform='translateY(-2px)'" onmouseout="this.style.borderColor='#e5e7eb'; this.style.transform='translateY(0)'">
                                    <div style="font-size: 2rem; margin-bottom: 8px;">${method.icon}</div>
                                    <div style="font-weight: 600; color: #111827;">${method.label}</div>
                                </button>
                            `).join('')}
                        </div>
                        <button id="cancelPaymentBtn" style="width: 100%; margin-top: 16px; padding: 12px; border: 2px solid #e5e7eb; background: white; border-radius: 8px; font-weight: 600; color: #6b7280; cursor: pointer;">Cancel</button>
                    </div>
                `;
                
                document.body.appendChild(modal);
                
                const closeModal = () => {
                    modal.style.animation = 'fadeOut 0.2s ease-out';
                    setTimeout(() => modal.remove(), 200);
                };
                
                document.getElementById('cancelPaymentBtn').onclick = closeModal;
                modal.onclick = (e) => {
                    if (e.target === modal) closeModal();
                };
                
                // Handle payment method selection
                modal.querySelectorAll('.payment-method-btn').forEach(btn => {
                    btn.onclick = async () => {
                        closeModal();
                        const paymentMethodStr = btn.dataset.method;
                        
                        const subtotal = window.posCart.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
                        const tax = subtotal * 0.05;
                        const total = subtotal + tax;
                        const selectedTable = document.getElementById('selectPosTable').value;
                        const orderType = selectedTable ? 'Dine-in' : 'Takeaway';
                        
                        const formData = new URLSearchParams();
                        formData.append('action', 'create_kot');
                        formData.append('tableId', selectedTable || '');
                        formData.append('orderType', orderType);
                        formData.append('customerName', '');
                        formData.append('paymentMethod', paymentMethodStr);
                        formData.append('cartItems', JSON.stringify(window.posCart));
                        formData.append('subtotal', subtotal.toFixed(2));
                        formData.append('tax', tax.toFixed(2));
                        formData.append('total', total.toFixed(2));
                        formData.append('notes', '');
                        
                        try {
                            const response = await fetch('../controllers/pos_operations.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: formData.toString()
                            });
                            const result = await response.json();
                            
                            if (result.success) {
                                const msg = result.kot_number
                                    ? `KOT #${result.kot_number} created! Order #${result.order_number}`
                                    : `Order #${result.order_number} created successfully!`;
                                showNotification(msg, 'success', 5000);
                                window.posCart = [];
                                updateWaiterPOSCart();
                                document.getElementById('selectPosTable').value = '';
                            } else {
                                showNotification(result.message || 'Failed to process payment', 'error');
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            showNotification('Network error. Please try again.', 'error');
                        }
                    };
                });
            });
        }
        
        // Load on page load
        loadWaiterRequests();
    </script>
</body>
</html>

