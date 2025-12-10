<?php
// Include secure session configuration
require_once __DIR__ . '/../config/session_config.php';
startSecureSession();

// Include authorization configuration
require_once __DIR__ . '/../config/authorization_config.php';

// Check if user is logged in (admin has user_id, staff has staff_id) and session is valid
// Redirect to login page if not logged in (for HTML pages, we redirect instead of returning JSON)
if (!isSessionValid() || (!isset($_SESSION['user_id']) && !isset($_SESSION['staff_id'])) || !isset($_SESSION['username']) || !isset($_SESSION['restaurant_id'])) {
    header('Location: ../admin/login.php');
    exit();
}

// Verify user has permission to view dashboard
// If not, redirect to login (they shouldn't be here)
try {
    requireLogin(false);
    requirePermission(PERMISSION_VIEW_DASHBOARD, false);
} catch (Exception $e) {
    // If permission denied, redirect to login
    header('Location: ../admin/login.php');
    exit();
}

// If staff member, redirect to their role-specific dashboard
if (isset($_SESSION['staff_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'Waiter':
            header('Location: waiter_dashboard.php');
            exit();
        case 'Chef':
            header('Location: chef_dashboard.php');
            exit();
        case 'Manager':
            header('Location: manager_dashboard.php');
            exit();
        // Admin staff can access main dashboard, so no redirect needed
    }
}

// Load restaurant info from database to prevent flash of default content
$restaurant_name = $_SESSION['restaurant_name'] ?? 'Restaurant Name';
$restaurant_id = $_SESSION['restaurant_id'] ?? 'RES001';
$restaurant_logo = '../assets/images/logo.png'; // Default fallback
// Try to get currency from session first (if saved), otherwise default
$currency_symbol = $_SESSION['currency_symbol'] ?? '₹'; // Default currency
$timezone = 'Asia/Kolkata'; // Default timezone
$user_email = '';
$user_phone = '';
$user_address = '';
$user_role = 'Administrator';

try {
    // Include database connection
    if (file_exists(__DIR__ . '/../db_connection.php')) {
        require_once __DIR__ . '/../db_connection.php';
    } else {
        // Fallback: try root directory
        $rootDir = dirname(__DIR__);
        if (file_exists($rootDir . '/db_connection.php')) {
            require_once $rootDir . '/db_connection.php';
        }
    }
    
    // Get connection from db_connection.php (use getConnection() for lazy connection support)
    if (function_exists('getConnection')) {
        $conn = getConnection();
    } else {
        // Fallback to $pdo if getConnection() doesn't exist (backward compatibility)
        $conn = $pdo ?? null;
        if (!$conn) {
            throw new Exception('Database connection not available');
        }
    }
    
    // Try to get all user settings from database to prevent FOUC
    // Load exactly like restaurant logo - server-side before HTML renders
    try {
        $stmt = $conn->prepare("SELECT id, restaurant_logo, currency_symbol, timezone, email, phone, address, role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($userRow) {
            // Restaurant logo - load exactly like this
            if (!empty($userRow['restaurant_logo'])) {
                // Check if logo is stored in database (starts with 'db:')
                if (strpos($userRow['restaurant_logo'], 'db:') === 0) {
                    // Use API endpoint for database-stored image
                    $restaurant_logo = '../api/image.php?type=logo&id=' . ($userRow['id'] ?? $_SESSION['user_id']);
                } elseif (strpos($userRow['restaurant_logo'], 'http') === 0) {
                    // External URL
                    $restaurant_logo = $userRow['restaurant_logo'];
                } else {
                    // File-based image (backward compatibility)
                    $restaurant_logo = $userRow['restaurant_logo'];
                    if (strpos($restaurant_logo, 'uploads/') === 0) {
                        $restaurant_logo = '../' . $restaurant_logo;
                    } elseif (strpos($restaurant_logo, '../') !== 0) {
                        $restaurant_logo = '../uploads/' . $restaurant_logo;
                    }
                }
            }
            // Currency symbol - load exactly like restaurant logo (server-side, no JavaScript needed)
            // This MUST be loaded before HTML renders to prevent any flash
            // IMPORTANT: Use array_key_exists to check if column exists, then check value
            if (array_key_exists('currency_symbol', $userRow) && $userRow['currency_symbol'] !== null && $userRow['currency_symbol'] !== '') {
                // Use centralized Unicode fix function
                require_once __DIR__ . '/../config/unicode_utils.php';
                $db_currency = fixCurrencySymbol($userRow['currency_symbol']);
                    $currency_symbol = htmlspecialchars($db_currency, ENT_QUOTES, 'UTF-8');
                    // Save to session for faster loading next time
                    $_SESSION['currency_symbol'] = $currency_symbol;
            }
            // Timezone
            if (!empty($userRow['timezone'])) {
                $timezone = htmlspecialchars($userRow['timezone']);
            }
            // User details
            $user_email = htmlspecialchars($userRow['email'] ?? '');
            $user_phone = htmlspecialchars($userRow['phone'] ?? '');
            $user_address = htmlspecialchars($userRow['address'] ?? '');
            $user_role = htmlspecialchars($userRow['role'] ?? 'Administrator');
        }
    } catch (PDOException $e) {
        // If columns don't exist, try without them
        try {
            $stmt = $conn->prepare("SELECT id, restaurant_logo, currency_symbol FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $logoRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($logoRow) {
                if (!empty($logoRow['restaurant_logo'])) {
                    // Check if logo is stored in database (starts with 'db:')
                    if (strpos($logoRow['restaurant_logo'], 'db:') === 0) {
                        // Use API endpoint for database-stored image
                        $restaurant_logo = '../api/image.php?type=logo&id=' . ($logoRow['id'] ?? $_SESSION['user_id']);
                    } elseif (strpos($logoRow['restaurant_logo'], 'http') === 0) {
                        // External URL
                        $restaurant_logo = $logoRow['restaurant_logo'];
                    } else {
                        // File-based image (backward compatibility)
                        $restaurant_logo = $logoRow['restaurant_logo'];
                        if (strpos($restaurant_logo, 'uploads/') === 0) {
                            $restaurant_logo = '../' . $restaurant_logo;
                        } elseif (strpos($restaurant_logo, '../') !== 0) {
                            $restaurant_logo = '../uploads/' . $restaurant_logo;
                        }
                    }
                }
                // Also try to get currency symbol
                if (array_key_exists('currency_symbol', $logoRow) && $logoRow['currency_symbol'] !== null && $logoRow['currency_symbol'] !== '') {
                    // Use centralized Unicode fix function
                    require_once __DIR__ . '/../config/unicode_utils.php';
                    $db_currency = fixCurrencySymbol($logoRow['currency_symbol']);
                        $currency_symbol = htmlspecialchars($db_currency, ENT_QUOTES, 'UTF-8');
                        // Save to session for faster loading next time
                        $_SESSION['currency_symbol'] = $currency_symbol;
                }
            }
        } catch (PDOException $e2) {
            // Use defaults - currency_symbol already has default '₹' set above
            $restaurant_logo = '../assets/images/logo.png';
        }
    }
} catch (Exception $e) {
    // If database query fails, use defaults
    $restaurant_logo = '../assets/images/logo.png';
}
?>
<!DOCTYPE html>
<!-- Coding By CodingNepal - youtube.com/@codingnepal -->
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>Restaurant Management System</title>
  
  <!-- Resource Hints for Performance -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="dns-prefetch" href="https://fonts.googleapis.com">
  <link rel="dns-prefetch" href="https://fonts.gstatic.com">
  
  <!-- Critical CSS -->
  <link rel="stylesheet" href="../assets/css/style.css">
  
  <!-- Optimized Font Loading -->
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0"></noscript>
  
  <!-- Cropper.js for image cropping (local files to avoid tracking prevention blocking) -->
  <link rel="stylesheet" href="../assets/libs/cropperjs/cropper.min.css">
  
  <!-- Scripts - Defer non-critical -->
  <script src="../assets/js/sweetalert2.all.min.js" defer></script>
  <script src="../assets/libs/cropperjs/cropper.min.js" defer></script>
  <script>
    // Currency symbol loaded from server-side PHP (exactly like restaurant logo/name)
    // NO JavaScript updates needed - value is already correct in HTML from PHP
    window.globalCurrencySymbol = <?php echo json_encode($currency_symbol, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
    localStorage.setItem('system_currency', window.globalCurrencySymbol);
  </script>
</head>
<body>
  <aside class="sidebar">
    <!-- Sidebar header -->
    <header class="sidebar-header">
      <a href="#" class="header-logo">
        <img id="dashboardRestaurantLogo" src="<?php echo htmlspecialchars($restaurant_logo) . (strpos($restaurant_logo, '?') !== false ? '&' : '?') . 't=' . (time() . '_' . mt_rand(1000, 9999)); ?>" alt="Restaurant Management" onerror="this.src='../assets/images/logo.png'; this.style.borderRadius='50%'; this.style.objectFit='cover';" style="border-radius: 50%; object-fit: cover; width: 46px; height: 46px;">
        <div class="restaurant-info">
          <div class="restaurant-name" id="restaurantName"><?php echo htmlspecialchars($restaurant_name); ?></div>
          <div class="restaurant-id" id="restaurantId"><?php echo htmlspecialchars($restaurant_id); ?></div>
        </div>
      </a>
      <button class="toggler sidebar-toggler">
        <span class="material-symbols-rounded">chevron_left</span>
      </button>
      <button class="toggler menu-toggler">
        <span class="material-symbols-rounded">menu</span>
      </button>
    </header>

    <nav class="sidebar-nav">
      <!-- Primary top nav -->
      <ul class="nav-list primary-nav">
        <li class="nav-item">
          <a href="#" class="nav-link" data-page="dashboardPage">
            <span class="nav-icon material-symbols-rounded">dashboard</span>
            <span class="nav-label">Dashboard</span>
          </a>
          <span class="nav-tooltip">Dashboard</span>
        </li>
        <li class="nav-item has-submenu">
          <a href="#" class="nav-link submenu-toggle">
            <span class="nav-icon material-symbols-rounded">menu</span>
            <span class="nav-label">Menu</span>
            <span class="submenu-arrow material-symbols-rounded">chevron_right</span>
          </a>
          <span class="nav-tooltip">Menu</span>
          <ul class="submenu">
            <li class="nav-item">
              <a href="#" class="nav-link submenu-link" data-page="menuPage">
                <span class="nav-icon material-symbols-rounded">menu</span>
                <span class="nav-label">Category</span>
              </a>
              <span class="nav-tooltip">Category</span>
            </li>
            <li class="nav-item">
              <a href="#" class="nav-link submenu-link" data-page="menuItemsPage">
                <span class="nav-icon material-symbols-rounded">list</span>
                <span class="nav-label">Menu Items</span>
              </a>
              <span class="nav-tooltip">Menu Items</span>
            </li>
          </ul>
        </li>
        <!-- Tables Menu with Submenus -->
        <li class="nav-item has-submenu">
          <a href="#" class="nav-link submenu-toggle">
            <span class="nav-icon material-symbols-rounded">table_chart</span>
            <span class="nav-label">Tables</span>
            <span class="submenu-arrow material-symbols-rounded">chevron_right</span>
          </a>
          <span class="nav-tooltip">Tables</span>
          <ul class="submenu">
            <li class="nav-item">
              <a href="#" class="nav-link submenu-link" data-page="areaPage">
                <span class="nav-icon material-symbols-rounded">area_chart</span>
                <span class="nav-label">Area</span>
              </a>
              <span class="nav-tooltip">Area</span>
            </li>
            <li class="nav-item">
              <a href="#" class="nav-link submenu-link" data-page="tablesPage">
                <span class="nav-icon material-symbols-rounded">table_rows</span>
                <span class="nav-label">Tables</span>
              </a>
              <span class="nav-tooltip">Tables</span>
            </li>
            <li class="nav-item">
              <a href="#" class="nav-link submenu-link" data-page="qrCodesPage">
                <span class="nav-icon material-symbols-rounded">qr_code</span>
                <span class="nav-label">QR Code</span>
              </a>
              <span class="nav-tooltip">QR Code</span>
            </li>
          </ul>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link" data-page="reservationsPage">
            <span class="nav-icon material-symbols-rounded">event</span>
            <span class="nav-label">Reservations</span>
          </a>
          <span class="nav-tooltip">Reservations</span>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link" data-page="posPage">
            <span class="nav-icon material-symbols-rounded">point_of_sale</span>
            <span class="nav-label">POS</span>
          </a>
          <span class="nav-tooltip">Point of Sale</span>
        </li>
        <li class="nav-item has-submenu">
          <a href="#" class="nav-link submenu-toggle">
            <span class="nav-icon material-symbols-rounded">receipt_long</span>
            <span class="nav-label">Orders</span>
            <span class="submenu-arrow material-symbols-rounded">chevron_right</span>
          </a>
          <span class="nav-tooltip">Orders</span>
          <ul class="submenu">
            <li class="nav-item">
              <a href="#" class="nav-link submenu-link" data-page="kotPage">
                <span class="nav-icon material-symbols-rounded">restaurant_menu</span>
                <span class="nav-label">KOT</span>
              </a>
              <span class="nav-tooltip">Kitchen Order Ticket</span>
            </li>
            <li class="nav-item">
              <a href="#" class="nav-link submenu-link" data-page="ordersPage">
                <span class="nav-icon material-symbols-rounded">receipt</span>
                <span class="nav-label">Orders</span>
              </a>
              <span class="nav-tooltip">Orders</span>
            </li>
          </ul>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link" data-page="customersPage">
            <span class="nav-icon material-symbols-rounded">people</span>
            <span class="nav-label">Customers</span>
          </a>
          <span class="nav-tooltip">Customers</span>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link" data-page="staffPage">
            <span class="nav-icon material-symbols-rounded">person</span>
            <span class="nav-label">Staff</span>
          </a>
          <span class="nav-tooltip">Staff</span>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link" data-page="waiterRequestsPage">
            <span class="nav-icon material-symbols-rounded">notifications_active</span>
            <span class="nav-label">Waiter Requests</span>
          </a>
          <span class="nav-tooltip">Waiter Requests</span>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link" data-page="paymentsPage">
            <span class="nav-icon material-symbols-rounded">payments</span>
            <span class="nav-label">Payments</span>
          </a>
          <span class="nav-tooltip">Payments</span>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link" data-page="reportsPage">
            <span class="nav-icon material-symbols-rounded">assessment</span>
            <span class="nav-label">Reports</span>
          </a>
          <span class="nav-tooltip">Reports</span>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link" data-page="settingsPage">
            <span class="nav-icon material-symbols-rounded">settings</span>
            <span class="nav-label">Settings</span>
          </a>
          <span class="nav-tooltip">Settings</span>
        </li>
        <li class="nav-item">
          <a href="../website/index.php?restaurant_id=<?php echo urlencode($restaurant_id); ?>&restaurant=<?php 
            $restaurant_slug = strtolower($restaurant_name);
            $restaurant_slug = preg_replace('/[^a-z0-9]+/', '-', $restaurant_slug);
            $restaurant_slug = trim($restaurant_slug, '-');
            echo urlencode($restaurant_slug);
          ?>" class="nav-link" target="_blank">
            <span class="nav-icon material-symbols-rounded">language</span>
            <span class="nav-label">Customer Website</span>
          </a>
          <span class="nav-tooltip">Customer Website</span>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link" data-page="websiteThemePage">
            <span class="nav-icon material-symbols-rounded">palette</span>
            <span class="nav-label">Website Appearance</span>
          </a>
          <span class="nav-tooltip">Website Appearance</span>
        </li>
      </ul>

      <!-- Secondary bottom nav -->
      <ul class="nav-list secondary-nav">
        <li class="nav-item">
          <a href="#" class="nav-link" data-page="profilePage">
            <span class="nav-icon material-symbols-rounded">account_circle</span>
            <span class="nav-label">Profile</span>
          </a>
          <span class="nav-tooltip">Profile</span>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link" onclick="logout()">
            <span class="nav-icon material-symbols-rounded">logout</span>
            <span class="nav-label">Logout</span>
          </a>
          <span class="nav-tooltip">Logout</span>
        </li>
      </ul>
    </nav>
  </aside>

  <!-- Main Content Area -->
  <main class="main-content">
    <!-- Dashboard Page (Default) -->
    <div id="dashboardPage" class="page active">
      <div class="page-header">
        <div class="dashboard-header-row">
          <div>
            <h1>Dashboard Overview</h1>
            <p id="dashboardTime">Welcome back! Here's what's happening today.</p>
            <div id="trialInfo" style="margin-top:.25rem;color:#92400e;font-weight:700;display:none;"></div>
          </div>
          <button class="btn-refresh-dashboard" onclick="loadDashboardStats()" title="Refresh">
            <span class="material-symbols-rounded">refresh</span>
          </button>
        </div>
      </div>
      <div class="page-content">
        <!-- Main Stats Row -->
        <div class="main-stats">
          <div class="main-stat-card revenue">
            <div class="stat-main">
              <span class="material-symbols-rounded">payments</span>
              <div class="stat-main-info">
                <div class="stat-label">Today's Revenue</div>
                <div class="stat-value-large" id="todayRevenue"><?php echo htmlspecialchars($currency_symbol); ?>0.00</div>
              </div>
            </div>
            <div class="stat-footer">
              <span class="material-symbols-rounded">trending_up</span>
              <span>View Reports</span>
            </div>
          </div>
          
          <div class="main-stat-card orders">
            <div class="stat-main">
              <span class="material-symbols-rounded">receipt_long</span>
              <div class="stat-main-info">
                <div class="stat-label">Today's Orders</div>
                <div class="stat-value-large" id="todayOrders">0</div>
              </div>
            </div>
            <div class="stat-footer">
              <span class="material-symbols-rounded">schedule</span>
              <span>Last 24 hours</span>
            </div>
          </div>
          
          <div class="main-stat-card kot">
            <div class="stat-main">
              <span class="material-symbols-rounded">restaurant_menu</span>
              <div class="stat-main-info">
                <div class="stat-label">Active KOT</div>
                <div class="stat-value-large" id="activeKOT">0</div>
              </div>
            </div>
            <div class="stat-footer">
              <span class="material-symbols-rounded">kitchen</span>
              <span id="kotStatus">In Progress</span>
            </div>
          </div>
        </div>
        
        <!-- Secondary Stats -->
        <div class="secondary-stats">
          <div class="secondary-stat-card">
            <div class="stat-icon-circle customers">
              <span class="material-symbols-rounded">people</span>
            </div>
            <div class="stat-info">
              <div class="stat-label">Customers</div>
              <div class="stat-value" id="totalCustomers">0</div>
            </div>
          </div>
          
          <div class="secondary-stat-card">
            <div class="stat-icon-circle tables">
              <span class="material-symbols-rounded">table_restaurant</span>
            </div>
            <div class="stat-info">
              <div class="stat-label">Tables</div>
              <div class="stat-value" id="tableInfo">0/0</div>
            </div>
          </div>
          
          <div class="secondary-stat-card">
            <div class="stat-icon-circle items">
              <span class="material-symbols-rounded">restaurant</span>
            </div>
            <div class="stat-info">
              <div class="stat-label">Menu Items</div>
              <div class="stat-value" id="totalItems">0</div>
            </div>
          </div>
          
          <div class="secondary-stat-card">
            <div class="stat-icon-circle pending">
              <span class="material-symbols-rounded">pending_actions</span>
            </div>
            <div class="stat-info">
              <div class="stat-label">Pending</div>
              <div class="stat-value" id="pendingOrders">0</div>
            </div>
          </div>
        </div>
        
        <!-- Content Grid -->
        <div class="dashboard-content-grid">
          <!-- Recent Orders Card -->
          <div class="dashboard-card-modern">
            <div class="card-header-modern">
              <h3>
                <span class="material-symbols-rounded">schedule</span>
                Recent Orders
              </h3>
              <button class="btn-view-all" onclick="showPage('ordersPage')">View All</button>
            </div>
            <div class="card-body-modern" id="recentOrders">
              <div class="loading">Loading...</div>
            </div>
          </div>
          
          <!-- Popular Items Card -->
          <div class="dashboard-card-modern">
            <div class="card-header-modern">
              <h3>
                <span class="material-symbols-rounded">local_fire_department</span>
                Popular Today
              </h3>
              <span class="badge-today">Today</span>
            </div>
            <div class="card-body-modern" id="popularItems">
              <div class="loading">Loading...</div>
            </div>
          </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="dashboard-card-modern">
          <div class="card-header-modern">
            <h3>
              <span class="material-symbols-rounded">rocket_launch</span>
              Quick Actions
            </h3>
          </div>
          <div class="quick-actions-grid">
            <button class="quick-action-btn" onclick="showPage('posPage')">
              <span class="material-symbols-rounded">point_of_sale</span>
              <span class="action-label">New Order</span>
            </button>
            <button class="quick-action-btn" onclick="showPage('kotPage')">
              <span class="material-symbols-rounded">restaurant_menu</span>
              <span class="action-label">KOT</span>
            </button>
            <button class="quick-action-btn" onclick="showPage('ordersPage')">
              <span class="material-symbols-rounded">receipt_long</span>
              <span class="action-label">Orders</span>
            </button>
            <button class="quick-action-btn" onclick="showPage('menuItemsPage')">
              <span class="material-symbols-rounded">menu_book</span>
              <span class="action-label">Menu</span>
            </button>
            <button class="quick-action-btn" onclick="showPage('tablesPage')">
              <span class="material-symbols-rounded">table_chart</span>
              <span class="action-label">Tables</span>
            </button>
            <button class="quick-action-btn" onclick="showPage('customersPage')">
              <span class="material-symbols-rounded">people</span>
              <span class="action-label">Customers</span>
            </button>
            <button class="quick-action-btn" onclick="showPage('staffPage')">
              <span class="material-symbols-rounded">groups</span>
              <span class="action-label">Staff</span>
            </button>
            <button class="quick-action-btn" onclick="showPage('reservationsPage')">
              <span class="material-symbols-rounded">event</span>
              <span class="action-label">Reservations</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Website Theme Page -->
    <div id="websiteThemePage" class="page">
      <div class="page-header">
        <h1>Website Appearance</h1>
        <p>Change the colors and banner image used in the customer website.</p>
      </div>
      <div class="page-content">
        <div class="settings-grid">
          <div class="settings-section">
            <h2 class="settings-section-title">
              <span class="material-symbols-rounded">palette</span>
              Colors
            </h2>
            
            <!-- Visual Preview -->
            <div id="colorPreviewContainer" style="background: white; border-radius: 8px; padding: 1rem; border: 2px solid #e5e7eb; margin-bottom: 2rem;">
              <div style="font-weight: 700; color: #111827; margin-bottom: 0.75rem;">Preview:</div>
              <div id="heroPreview" style="background: linear-gradient(135deg, #F70000 0%, #DA020E 100%); border-radius: 8px; padding: 1.5rem; color: white; margin-bottom: 1rem;">
                <div style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">Restaurant Name</div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Hero Section Background (Gradient)</div>
              </div>
              <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <div id="categoryButtonPreview" style="border: 2px solid #F70000; color: #F70000; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; font-size: 0.875rem;">Category Button</div>
                <div id="addToCartPreview" style="background: #FFD100; color: #333; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; font-size: 0.875rem;">Add to Cart</div>
                <div id="checkoutPreview" style="background: #F70000; color: white; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; font-size: 0.875rem;">Checkout</div>
              </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
              <div style="background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%); border-radius: 16px; padding: 1.5rem; border: 2px solid #e5e7eb; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)'">
                <div style="text-align: center; margin-bottom: 1rem;">
                  <div style="font-weight: 700; color: #111827; font-size: 1rem; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <span class="material-symbols-rounded" style="font-size: 1.2rem; color: #dc2626;">palette</span>
                    Main Color
                  </div>
                  <div style="font-size: 0.8rem; color: #6b7280; margin-bottom: 0.75rem;">Primary brand color</div>
                </div>
                <input type="color" id="primaryRed" value="#F70000" style="width: 100%; height: 70px; border-radius: 12px; border: 3px solid #e5e7eb; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" onmouseover="this.style.borderColor='#d1d5db'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.borderColor='#e5e7eb'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">
                <div id="primaryRedDisplay" style="margin-top: 0.75rem; font-family: 'Courier New', monospace; font-size: 0.9rem; color: #374151; font-weight: 600; text-align: center; background: #f3f4f6; padding: 0.5rem; border-radius: 8px;">#F70000</div>
              </div>
              
              <div style="background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%); border-radius: 16px; padding: 1.5rem; border: 2px solid #e5e7eb; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)'">
                <div style="text-align: center; margin-bottom: 1rem;">
                  <div style="font-weight: 700; color: #111827; font-size: 1rem; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <span class="material-symbols-rounded" style="font-size: 1.2rem; color: #991b1b;">gradient</span>
                    Accent Color
                  </div>
                  <div style="font-size: 0.8rem; color: #6b7280; margin-bottom: 0.75rem;">Darker shade for gradients</div>
                </div>
                <input type="color" id="darkRed" value="#DA020E" style="width: 100%; height: 70px; border-radius: 12px; border: 3px solid #e5e7eb; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" onmouseover="this.style.borderColor='#d1d5db'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.borderColor='#e5e7eb'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">
                <div id="darkRedDisplay" style="margin-top: 0.75rem; font-family: 'Courier New', monospace; font-size: 0.9rem; color: #374151; font-weight: 600; text-align: center; background: #f3f4f6; padding: 0.5rem; border-radius: 8px;">#DA020E</div>
              </div>
              
              <div style="background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%); border-radius: 16px; padding: 1.5rem; border: 2px solid #e5e7eb; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)'">
                <div style="text-align: center; margin-bottom: 1rem;">
                  <div style="font-weight: 700; color: #111827; font-size: 1rem; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <span class="material-symbols-rounded" style="font-size: 1.2rem; color: #fbbf24;">star</span>
                    Highlight Color
                  </div>
                  <div style="font-size: 0.8rem; color: #6b7280; margin-bottom: 0.75rem;">Call-to-action buttons</div>
                </div>
                <input type="color" id="primaryYellow" value="#FFD100" style="width: 100%; height: 70px; border-radius: 12px; border: 3px solid #e5e7eb; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" onmouseover="this.style.borderColor='#d1d5db'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.borderColor='#e5e7eb'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">
                <div id="primaryYellowDisplay" style="margin-top: 0.75rem; font-family: 'Courier New', monospace; font-size: 0.9rem; color: #374151; font-weight: 600; text-align: center; background: #f3f4f6; padding: 0.5rem; border-radius: 8px;">#FFD100</div>
              </div>
            </div>
            <div class="form-actions">
              <button type="button" class="btn btn-save" id="saveWebsiteThemeBtn">Save Theme</button>
              <a href="../website/index.php?restaurant_id=<?php echo urlencode($restaurant_id); ?>&restaurant=<?php 
                $restaurant_slug = strtolower($restaurant_name);
                $restaurant_slug = preg_replace('/[^a-z0-9]+/', '-', $restaurant_slug);
                $restaurant_slug = trim($restaurant_slug, '-');
                echo urlencode($restaurant_slug);
              ?>" class="btn btn-primary" target="_blank">Open Website</a>
            </div>
            <p style="margin-top:10px;color:#666;">Saved locally on this server (no link parameters required). The website reads saved colors automatically from the same origin.</p>
            
            <!-- Restaurant Website Link -->
            <div style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 12px; border: 2px solid #e5e7eb;">
              <h3 style="margin: 0 0 1rem 0; font-size: 1.1rem; color: #111827; display: flex; align-items: center; gap: 0.5rem;">
                <span class="material-symbols-rounded" style="font-size: 1.3rem; color: var(--primary-red);">link</span>
                Your Restaurant Website Link
              </h3>
              <p style="margin: 0 0 1rem 0; color: #6b7280; font-size: 0.9rem;">Share this unique link with your customers. Each restaurant has its own unique URL.</p>
              <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <input type="text" id="restaurantWebsiteLink" readonly value="<?php 
                  $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                  $restaurant_slug = strtolower($restaurant_name);
                  $restaurant_slug = preg_replace('/[^a-z0-9]+/', '-', $restaurant_slug);
                  $restaurant_slug = trim($restaurant_slug, '-');
                  $script_path = dirname($_SERVER['PHP_SELF']);
                  echo htmlspecialchars($base_url . $script_path . '/../website/index.php?restaurant_id=' . urlencode($restaurant_id) . '&restaurant=' . urlencode($restaurant_slug));
                ?>" style="flex: 1; min-width: 300px; padding: 0.75rem; border: 2px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; background: white; color: #111827;">
                <button type="button" class="btn btn-primary" onclick="copyRestaurantLink()" style="white-space: nowrap;">
                  <span class="material-symbols-rounded">content_copy</span>
                  Copy Link
                </button>
              </div>
              <p style="margin: 0.75rem 0 0 0; color: #6b7280; font-size: 0.85rem;">
                <strong>Short URL format:</strong> <code style="background: #e5e7eb; padding: 0.2rem 0.4rem; border-radius: 4px; font-size: 0.85rem;"><?php 
                  $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                  $script_path = dirname($_SERVER['PHP_SELF']);
                  echo htmlspecialchars($base_url . $script_path . '/../website/' . urlencode($restaurant_slug));
                ?></code>
              </p>
            </div>
          </div>
          
          <div class="settings-section">
            <h2 class="settings-section-title">
              <span class="material-symbols-rounded">image</span>
              Banner Images (Slideshow)
            </h2>
            <div class="form-group">
              <label for="bannerUpload">Upload Banner Images</label>
              <p style="color:#666;font-size:0.9rem;margin-bottom:10px;">Upload multiple banner images to display as a slideshow on your website. Each image will display for 3 seconds. Recommended size: 1200x300px or similar. Max size: 5MB per image</p>
              <input type="file" id="bannerUpload" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" multiple style="margin-bottom:10px;">
              <button type="button" class="btn btn-primary" id="uploadBannerBtn" style="margin-bottom:10px;">
                <span class="material-symbols-rounded">upload</span>
                Upload Banners
              </button>
            </div>
            <div id="bannersPreview" style="margin-top:20px;display:block;">
              <label style="display:block;margin-bottom:10px;font-weight:600;">Banner Previews:</label>
              <div id="bannersGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:20px;margin-top:10px;min-height:50px;">
                <p style="color:#666;grid-column:1/-1;text-align:center;padding:20px;">Loading banners...</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Category Management Page -->
    <div id="menuPage" class="page">
      <div class="page-header">
        <h1>Category Management</h1>
        <p>Create, edit, and manage your menus</p>
      </div>
      <div class="page-content">
        <div class="menu-actions">
          <button class="btn btn-primary" id="addMenuBtn">
            <span class="material-symbols-rounded">add</span>
            Add New Category
          </button>
        </div>
        
        <div class="menu-list" id="menuList">
          <!-- Menus will be loaded here dynamically -->
          <div class="loading">Loading menus...</div>
        </div>
      </div>
    </div>

    <!-- Menu Items Page -->
    <div id="menuItemsPage" class="page">
      <div class="page-header">
        <h1>Menu Items Management</h1>
        <p>Create, edit, and manage your menu items</p>
      </div>
      <div class="page-content">
        <div class="menu-items-actions">
          <button class="btn btn-primary" id="addMenuItemBtn">
            <span class="material-symbols-rounded">add</span>
            Add New Menu Item
          </button>
          
          <div class="filters">
            <select id="menuFilter" class="filter-select">
              <option value="">All Menus</option>
            </select>
            <select id="categoryFilter" class="filter-select">
              <option value="">All Categories</option>
            </select>
            <select id="typeFilter" class="filter-select">
              <option value="">All Types</option>
              <option value="Veg">Veg</option>
              <option value="Non Veg">Non Veg</option>
              <option value="Egg">Egg</option>
              <option value="Drink">Drink</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </div>
        
        <div class="menu-items-list" id="menuItemsList">
          <!-- Menu items will be loaded here dynamically -->
          <div class="loading">Loading menu items...</div>
        </div>
      </div>
    </div>

    <!-- Payments Page -->
    <div id="paymentsPage" class="page">
      <div class="page-header">
        <h1>Payment Transactions</h1>
        <p>View and manage all payment transactions</p>
      </div>
      <div class="page-content">
        <!-- Filters -->
        <div style="display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;">
          <input type="text" id="paymentSearch" placeholder="Search by order, amount..." style="flex: 1; min-width: 250px; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px;" />
          <select id="paymentMethodFilter" style="padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px;">
            <option value="">All Methods</option>
            <option value="Cash">Cash</option>
            <option value="Card">Card</option>
            <option value="UPI">UPI</option>
            <option value="Online">Online</option>
            <option value="Wallet">Wallet</option>
          </select>
          <select id="paymentStatusFilter" style="padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px;">
            <option value="">All Status</option>
            <option value="Success">Success</option>
            <option value="Failed">Failed</option>
            <option value="Pending">Pending</option>
            <option value="Refunded">Refunded</option>
          </select>
          <button onclick="exportPaymentsToCSV()" style="padding: 0.75rem 1.5rem; background: #28a745; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
            <span class="material-symbols-rounded" style="font-size: 1rem;">download</span>
            Export CSV
          </button>
        </div>

        <!-- Payments Table -->
        <div class="card">
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Amount</th>
                <th>Payment Method</th>
                <th>Transaction ID</th>
                <th>Order</th>
                <th>Status</th>
                <th>Date & Time</th>
              </tr>
            </thead>
            <tbody id="paymentsTableBody">
              <tr>
                <td colspan="7" style="text-align: center; padding: 2rem;">
                  <div class="loading">Loading payments...</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Reports Page -->
    <div id="reportsPage" class="page">
      <div class="page-header">
        <h1>Sales Reports</h1>
        <p>View detailed sales reports and analytics</p>
      </div>
      <div class="page-content">
        <!-- Report Filter Controls -->
        <div class="report-filters" style="display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
          <div style="flex: 1; min-width: 200px;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Period</label>
            <select id="reportPeriod" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
              <option value="today">Today</option>
              <option value="week">This Week</option>
              <option value="month">This Month</option>
              <option value="year">This Year</option>
              <option value="custom">Custom Date Range</option>
            </select>
          </div>
          <div id="customDateRange" style="display: none; flex: 1; min-width: 200px;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Start Date</label>
            <input type="date" id="reportStartDate" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
          </div>
          <div id="customDateRangeEnd" style="display: none; flex: 1; min-width: 200px;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">End Date</label>
            <input type="date" id="reportEndDate" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
          </div>
          <div style="flex: 1; min-width: 200px;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Report Type</label>
            <select id="reportType" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
              <option value="sales">Sales Report</option>
              <option value="customers">Customer Report</option>
              <option value="items">Top Items Report</option>
              <option value="payment">Payment Methods Report</option>
              <option value="hourly">Hourly Sales Report</option>
              <option value="staff">Staff Performance Report</option>
            </select>
          </div>
          <div id="paymentMethodFilter" style="flex: 1; min-width: 200px; display: none;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Payment Method</label>
            <select id="filterPaymentMethod" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
              <option value="all">All Methods</option>
              <option value="Cash">Cash</option>
              <option value="Card">Card</option>
              <option value="UPI">UPI</option>
              <option value="Online">Online</option>
            </select>
          </div>
          <div style="display: flex; align-items: flex-end; gap: 0.5rem;">
            <button onclick="loadReports()" style="padding: 0.75rem 2rem; background: var(--primary-red); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
              <span class="material-symbols-rounded">refresh</span> Refresh
            </button>
            <button onclick="exportReportsToCSV()" style="padding: 0.75rem 2rem; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
              <span class="material-symbols-rounded">download</span> Export CSV
            </button>
          </div>
        </div>

        <!-- Sales Summary Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
          <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 1rem;">
              <div style="width: 50px; height: 50px; background: #ffe5e5; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <span class="material-symbols-rounded" style="color: var(--primary-red);">payments</span>
              </div>
              <div>
                <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.25rem;">Total Sales</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-red);" id="reportTotalSales"><?php echo htmlspecialchars($currency_symbol); ?>0.00</div>
              </div>
            </div>
          </div>
          
          <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 1rem;">
              <div style="width: 50px; height: 50px; background: #e5f3ff; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <span class="material-symbols-rounded" style="color: #0066cc;">receipt_long</span>
              </div>
              <div>
                <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.25rem;">Total Orders</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #0066cc;" id="reportTotalOrders">0</div>
              </div>
            </div>
          </div>
          
          <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 1rem;">
              <div style="width: 50px; height: 50px; background: #e5f7e5; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <span class="material-symbols-rounded" style="color: #28a745;">shopping_bag</span>
              </div>
              <div>
                <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.25rem;">Items Sold</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #28a745;" id="reportTotalItems">0</div>
              </div>
            </div>
          </div>
          
          <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 1rem;">
              <div style="width: 50px; height: 50px; background: #fff3cd; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <span class="material-symbols-rounded" style="color: #ffc107;">people</span>
              </div>
              <div>
                <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.25rem;">Customers</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #ffc107;" id="reportTotalCustomers">0</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sales Data Table -->
        <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
          <div style="padding: 1.5rem; border-bottom: 2px solid var(--light-gray);">
            <h2 style="margin: 0; font-size: 1.3rem; color: var(--primary-red);">Sales Details</h2>
          </div>
          <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
              <thead>
                <tr style="background: var(--light-gray);">
                  <th style="padding: 1rem; text-align: left; font-weight: 600;">Date</th>
                  <th style="padding: 1rem; text-align: left; font-weight: 600;">Order #</th>
                  <th style="padding: 1rem; text-align: left; font-weight: 600;">Customer</th>
                  <th style="padding: 1rem; text-align: left; font-weight: 600;">Items</th>
                  <th style="padding: 1rem; text-align: left; font-weight: 600;">Payment</th>
                  <th style="padding: 1rem; text-align: right; font-weight: 600;">Amount</th>
                </tr>
              </thead>
              <tbody id="reportSalesTable">
                <tr>
                  <td colspan="6" style="padding: 2rem; text-align: center; color: #666;">Loading sales data...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Bottom Charts -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
          <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="padding: 1.5rem; border-bottom: 2px solid var(--light-gray);">
              <h2 style="margin: 0; font-size: 1.3rem; color: var(--primary-red);">Top Selling Items</h2>
            </div>
            <div id="reportTopItems" style="padding: 1rem;">
              <div style="text-align: center; padding: 2rem; color: #666;">Loading...</div>
            </div>
          </div>
          
          <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="padding: 1.5rem; border-bottom: 2px solid var(--light-gray);">
              <h2 style="margin: 0; font-size: 1.3rem; color: var(--primary-red);">Payment Methods</h2>
            </div>
            <div id="reportPaymentMethods" style="padding: 1rem;">
              <div style="text-align: center; padding: 2rem; color: #666;">Loading...</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Settings Page -->
    <div id="settingsPage" class="page">
      <div class="page-header">
        <h1>Settings</h1>
        <p>Manage your restaurant settings and preferences</p>
      </div>
      <div class="page-content">
        <div class="settings-container">
          <!-- Restaurant Information Card -->
          <div class="profile-card-modern">
            <div class="profile-card-header">
              <h3>
                <span class="material-symbols-rounded">restaurant</span>
                Restaurant Information
              </h3>
              <p class="card-description">Update your restaurant details and contact information</p>
            </div>
            <div class="profile-card-body">
              <form id="restaurantSettingsForm">
                <div class="form-group">
                  <label for="restaurantNameSetting">
                    <span class="material-symbols-rounded">store</span>
                    Restaurant Name
                  </label>
                  <input type="text" id="restaurantNameSetting" placeholder="Enter restaurant name" required>
                </div>
                
                <div class="form-group">
                  <label for="restaurantIdSetting">
                    <span class="material-symbols-rounded">badge</span>
                    Restaurant ID
                  </label>
                  <input type="text" id="restaurantIdSetting" placeholder="Restaurant ID" readonly style="background: #f3f4f6; cursor: not-allowed;">
                </div>
                
                <div class="form-group">
                  <label for="restaurantAddress">
                    <span class="material-symbols-rounded">location_on</span>
                    Address
                  </label>
                  <textarea id="restaurantAddress" rows="3" placeholder="Enter restaurant address"></textarea>
                </div>
                
                <div class="form-row">
                  <div class="form-group">
                    <label for="restaurantPhone">
                      <span class="material-symbols-rounded">phone</span>
                      Phone Number
                    </label>
                    <input type="tel" id="restaurantPhone" placeholder="Enter phone number">
                  </div>
                  <div class="form-group">
                    <label for="restaurantEmail">
                      <span class="material-symbols-rounded">email</span>
                      Email Address
                    </label>
                    <input type="email" id="restaurantEmail" placeholder="Enter email address">
                  </div>
                </div>
                
                <div class="form-actions">
                  <button type="submit" class="btn btn-save">
                    <span class="material-symbols-rounded">save</span>
                    Save Changes
                  </button>
                </div>
              </form>
            </div>
          </div>
          
          <!-- Profile Settings Card -->
          <div class="profile-card-modern">
            <div class="profile-card-header">
              <h3>
                <span class="material-symbols-rounded">account_circle</span>
                Profile Settings
              </h3>
              <p class="card-description">Manage your account username and email</p>
            </div>
            <div class="profile-card-body">
              <form id="profileSettingsForm">
                <div class="form-group">
                  <label for="usernameSetting">
                    <span class="material-symbols-rounded">badge</span>
                    Username
                  </label>
                  <input type="text" id="usernameSetting" placeholder="Enter username" required>
                </div>
                
                <div class="form-group">
                  <label for="profileEmailSetting">
                    <span class="material-symbols-rounded">email</span>
                    Email Address
                  </label>
                  <input type="email" id="profileEmailSetting" placeholder="Enter email address" required>
                </div>
                
                <div class="form-group">
                  <label class="checkbox-label">
                    <input type="checkbox" id="emailNotifications">
                    <span class="checkmark"></span>
                    <span>Enable Email Notifications</span>
                  </label>
                </div>
                
                <div class="form-actions">
                  <button type="submit" class="btn btn-save">
                    <span class="material-symbols-rounded">save</span>
                    Update Profile
                  </button>
                </div>
              </form>
            </div>
          </div>
          
          <!-- System Settings Card -->
          <div class="profile-card-modern">
            <div class="profile-card-header">
              <h3>
                <span class="material-symbols-rounded">tune</span>
                System Settings
              </h3>
              <p class="card-description">Configure system preferences and defaults</p>
            </div>
            <div class="profile-card-body">
              <form id="systemSettingsForm">
                <div class="form-group">
                  <label for="currencySymbolSelect">
                    <span class="material-symbols-rounded">currency_exchange</span>
                    Currency Symbol
                  </label>
                  <select id="currencySymbolSelect">
                    <?php
                    $majorCurrencies = [
                      '₹' => '₹ Indian Rupee (INR)',
                      '$' => '$ US Dollar (USD)',
                      '€' => '€ Euro (EUR)',
                      '£' => '£ British Pound (GBP)',
                      '¥' => '¥ Japanese Yen (JPY)',
                      'A$' => 'A$ Australian Dollar (AUD)',
                      'C$' => 'C$ Canadian Dollar (CAD)',
                      'CHF' => 'CHF Swiss Franc',
                      'CN¥' => 'CN¥ Chinese Yuan (CNY)',
                      'HK$' => 'HK$ Hong Kong Dollar (HKD)',
                      'NZ$' => 'NZ$ New Zealand Dollar (NZD)',
                      'S$' => 'S$ Singapore Dollar (SGD)',
                      '₽' => '₽ Russian Ruble (RUB)',
                      '₩' => '₩ South Korean Won (KRW)',
                      'R' => 'R South African Rand (ZAR)',
                      '₦' => '₦ Nigerian Naira (NGN)',
                      '₨' => '₨ Pakistani Rupee (PKR)',
                      '৳' => '৳ Bangladeshi Taka (BDT)',
                      'Rs' => 'Rs Sri Lankan Rupee (LKR)',
                      'Custom' => 'Custom...'
                    ];
                    $isCustom = true;
                    foreach ($majorCurrencies as $symbol => $label) {
                      if ($symbol === 'Custom') continue;
                      if ($currency_symbol === $symbol) {
                        $isCustom = false;
                        echo '<option value="' . htmlspecialchars($symbol) . '" selected>' . htmlspecialchars($label) . '</option>';
                      } else {
                        echo '<option value="' . htmlspecialchars($symbol) . '">' . htmlspecialchars($label) . '</option>';
                      }
                    }
                    echo '<option value="Custom"' . ($isCustom ? ' selected' : '') . '>Custom...</option>';
                    ?>
                  </select>
                  <input type="text" id="currencySymbol" value="<?php echo $isCustom ? htmlspecialchars($currency_symbol) : ''; ?>" maxlength="10" placeholder="Enter custom currency symbol" class="currency-custom-input" style="<?php echo $isCustom ? '' : 'display: none;'; ?>">
                </div>
                
                <div class="form-group">
                  <label for="businessQRUpload">
                    <span class="material-symbols-rounded">qr_code</span>
                    Business Payment QR Code
                  </label>
                  <p style="color:#666;font-size:0.85rem;margin-bottom:0.75rem;">Upload your business payment QR code (UPI, Paytm, etc.) to display on the website. Max size: 5MB</p>
                  <div id="businessQRPreview" style="margin-bottom:0.75rem;display:none;">
                    <img id="businessQRPreviewImg" src="" alt="QR Code Preview" style="max-width:200px;max-height:200px;border-radius:8px;border:2px solid #e5e7eb;padding:0.5rem;background:#f9fafb;">
                    <button type="button" onclick="removeBusinessQR()" style="margin-top:0.5rem;padding:0.5rem 1rem;background:#fee2e2;color:#b91c1c;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:0.85rem;">
                      <span class="material-symbols-rounded" style="font-size:1rem;vertical-align:middle;">delete</span>
                      Remove QR Code
                    </button>
                  </div>
                  <input type="file" id="businessQRUpload" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" style="margin-bottom:0.5rem;">
                  <button type="button" class="btn btn-primary" id="uploadBusinessQRBtn" style="margin-top:0.5rem;">
                    <span class="material-symbols-rounded">upload</span>
                    Upload QR Code
                  </button>
                </div>
                
                <div class="form-group">
                  <label for="timezone">
                    <span class="material-symbols-rounded">schedule</span>
                    Timezone
                  </label>
                  <select id="timezone">
                    <option value="Asia/Kolkata" <?php echo $timezone === 'Asia/Kolkata' ? 'selected' : ''; ?>>Asia/Kolkata (IST)</option>
                    <option value="UTC" <?php echo $timezone === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                    <option value="America/New_York" <?php echo $timezone === 'America/New_York' ? 'selected' : ''; ?>>America/New_York (EST)</option>
                    <option value="Europe/London" <?php echo $timezone === 'Europe/London' ? 'selected' : ''; ?>>Europe/London (GMT)</option>
                  </select>
                </div>
                
                <div class="form-group">
                  <label class="checkbox-label">
                    <input type="checkbox" id="autoSync">
                    <span class="checkmark"></span>
                    <span>Enable Auto Sync</span>
                  </label>
                </div>
                
                <div class="form-group">
                  <label class="checkbox-label">
                    <input type="checkbox" id="notifications">
                    <span class="checkmark"></span>
                    <span>Enable Push Notifications</span>
                  </label>
                </div>
                
                <div class="form-actions">
                  <button type="submit" class="btn btn-save">
                    <span class="material-symbols-rounded">save</span>
                    Save Changes
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Profile Page -->
    <div id="profilePage" class="page">
      <div class="page-header">
        <h1>My Profile</h1>
        <p>Manage your account information and preferences</p>
      </div>
      <div class="page-content">
        <div class="profile-container">
          <!-- Profile Header Section -->
          <div class="profile-header-card">
            <div class="profile-avatar-section">
              <div class="profile-avatar-large" id="profileAvatarContainer">
                <img id="profileRestaurantLogo" src="" alt="Restaurant Logo" style="display: none; width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                <span id="profileInitials">JD</span>
              </div>
              <button class="btn-edit-avatar" onclick="openLogoUploadModal()" title="Change Restaurant Logo">
                <span class="material-symbols-rounded">photo_camera</span>
              </button>
            </div>
            <div class="profile-info-section">
              <div class="profile-title-row">
                <h2 id="profileName">Loading...</h2>
                <span class="profile-status-pill" id="profileSubscriptionStatusBadge">Loading...</span>
              </div>
              <p id="profileRole" class="profile-role-badge">Administrator</p>
              <p class="profile-email" id="profileEmail">Loading...</p>
              <div class="profile-details-list">
                <div class="profile-detail-item">
                  <span class="material-symbols-rounded">restaurant</span>
                  <span>Restaurant ID: <strong id="profileRestaurantName">Loading...</strong></span>
                </div>
                <div class="profile-detail-item">
                  <span class="material-symbols-rounded">call</span>
                  <span>Phone: <strong id="profilePhoneValueInline">Not added</strong></span>
                </div>
                <div class="profile-detail-item">
                  <span class="material-symbols-rounded">schedule</span>
                  <span>Timezone: <strong id="profileTimezoneTextInline">--</strong></span>
                </div>
                <div class="profile-detail-item">
                  <span class="material-symbols-rounded">calendar_today</span>
                  <span>Member Since: <strong id="profileMemberSinceDate">Loading...</strong></span>
                </div>
              </div>
            </div>
            <div class="profile-actions-section">
              <button class="btn btn-primary" id="editProfileBtn" onclick="showPage('settingsPage')">
                <span class="material-symbols-rounded">edit</span>
                Edit Profile
              </button>
              <button class="btn btn-secondary" type="button" onclick="showPage('settingsPage')">
                <span class="material-symbols-rounded">workspace_premium</span>
                Manage Subscription
              </button>
            </div>
          </div>

          <div class="profile-highlight-grid">
            <div class="profile-highlight-card">
              <div class="highlight-icon success">
                <span class="material-symbols-rounded">verified</span>
              </div>
              <div>
                <p class="highlight-label">Subscription</p>
                <h3 id="profileSubscriptionStatusText">Loading...</h3>
                <p class="highlight-subtext">Renews on <strong id="profileRenewalDateText">--</strong></p>
              </div>
            </div>
            <div class="profile-highlight-card">
              <div class="highlight-icon warning">
                <span class="material-symbols-rounded">calendar_month</span>
              </div>
              <div>
                <p class="highlight-label">Trial Ends</p>
                <h3 id="profileTrialEndText">--</h3>
                <p class="highlight-subtext">Timezone <strong id="profileTimezoneText">--</strong></p>
              </div>
            </div>
            <div class="profile-highlight-card">
              <div class="highlight-icon info">
                <span class="material-symbols-rounded">event_available</span>
              </div>
              <div>
                <p class="highlight-label">Member Since</p>
                <h3 id="profileMemberSinceHighlight">--</h3>
                <p class="highlight-subtext">Restaurant ID <strong id="profileRestaurantIdHighlight">--</strong></p>
              </div>
            </div>
          </div>

          <!-- Profile Content Grid -->
          <div class="profile-content-grid">
            <!-- Contact Card -->
            <div class="profile-card-modern profile-contact-card">
              <div class="profile-card-header">
                <h3>
                  <span class="material-symbols-rounded">contact_page</span>
                  Contact Details
                </h3>
                <p class="card-description">Keep your contact information up to date</p>
              </div>
              <div class="profile-card-body">
                <div class="profile-contact-row">
                  <div class="contact-icon accent">
                    <span class="material-symbols-rounded">call</span>
                  </div>
                  <div>
                    <p class="contact-label">Phone Number</p>
                    <strong id="profilePhoneValue">Not added</strong>
                  </div>
                </div>
                <div class="profile-contact-row">
                  <div class="contact-icon info">
                    <span class="material-symbols-rounded">email</span>
                  </div>
                  <div>
                    <p class="contact-label">Email Address</p>
                    <strong id="profileEmailValue">Not added</strong>
                  </div>
                </div>
                <div class="profile-contact-row">
                  <div class="contact-icon muted">
                    <span class="material-symbols-rounded">location_on</span>
                  </div>
                  <div>
                    <p class="contact-label">Address</p>
                    <strong id="profileAddressValue">Add your restaurant address</strong>
                  </div>
                </div>
              </div>
            </div>
            <!-- Edit Profile Form (Hidden by default) -->
            <div class="profile-card-modern" id="editProfileCard" style="display: none;">
              <div class="profile-card-header">
                <h3>
                  <span class="material-symbols-rounded">edit</span>
                  Edit Profile Information
                </h3>
              </div>
              <div class="profile-card-body">
                <form id="editProfileForm">
                  <div class="form-group">
                    <label for="editUsername">
                      <span class="material-symbols-rounded">badge</span>
                      Username
                    </label>
                    <input type="text" id="editUsername" placeholder="Enter username" required>
                  </div>
                  <div class="form-group">
                    <label for="editEmail">
                      <span class="material-symbols-rounded">email</span>
                      Email Address
                    </label>
                    <input type="email" id="editEmail" placeholder="Enter email address" required>
                  </div>
                  <div class="form-actions">
                    <button type="button" class="btn btn-cancel" onclick="cancelProfileEdit()">
                      <span class="material-symbols-rounded">close</span>
                      Cancel
                    </button>
                    <button type="submit" class="btn btn-save">
                      <span class="material-symbols-rounded">save</span>
                      Save Changes
                    </button>
                  </div>
                </form>
              </div>
            </div>

            <!-- Change Password Card -->
            <div class="profile-card-modern">
              <div class="profile-card-header">
                <h3>
                  <span class="material-symbols-rounded">lock</span>
                  Change Password
                </h3>
              </div>
              <div class="profile-card-body">
                <form id="changePasswordForm">
                  <div class="form-group">
                    <label for="currentPassword">
                      <span class="material-symbols-rounded">lock</span>
                      Current Password
                    </label>
                    <input type="password" id="currentPassword" placeholder="Enter current password" required>
                    <small class="form-error" id="currentPasswordError" style="display: none; color: #ef4444; margin-top: 0.5rem; font-size: 0.875rem;"></small>
                  </div>
                  <div class="form-group">
                    <label for="newPassword">
                      <span class="material-symbols-rounded">lock_reset</span>
                      New Password
                    </label>
                    <input type="password" id="newPassword" placeholder="Enter new password" required minlength="6">
                    <div class="password-criteria" id="passwordCriteria">
                      <small class="form-hint" style="display: block; margin-top: 0.5rem; color: #6b7280; font-size: 0.875rem;">Password must meet the following criteria:</small>
                      <ul class="criteria-list" style="margin: 0.5rem 0 0 1.25rem; padding: 0; list-style: none;">
                        <li class="criteria-item" data-criteria="length">
                          <span class="material-symbols-rounded criteria-icon" style="font-size: 1rem; vertical-align: middle; margin-right: 0.25rem;">close</span>
                          <span>At least 6 characters long</span>
                        </li>
                      </ul>
                    </div>
                    <small class="form-error" id="newPasswordError" style="display: none; color: #ef4444; margin-top: 0.5rem; font-size: 0.875rem;"></small>
                  </div>
                  <div class="form-group">
                    <label for="confirmPassword">
                      <span class="material-symbols-rounded">verified</span>
                      Confirm New Password
                    </label>
                    <input type="password" id="confirmPassword" placeholder="Confirm new password" required minlength="6">
                    <small class="form-hint" id="passwordMatchStatus" style="display: none; margin-top: 0.5rem; font-size: 0.875rem;"></small>
                    <small class="form-error" id="confirmPasswordError" style="display: none; color: #ef4444; margin-top: 0.5rem; font-size: 0.875rem;"></small>
                  </div>
                  <div class="form-actions">
                    <button type="submit" class="btn btn-save" id="changePasswordBtn">
                      <span class="material-symbols-rounded">lock</span>
                      Change Password
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Area Management Page -->
    <div id="areaPage" class="page">
      <div class="page-header">
        <h1>Area Management</h1>
        <p>Create, edit, and manage your restaurant areas</p>
      </div>
      <div class="page-content">
        <div class="menu-actions">
          <button class="btn btn-primary" id="addAreaBtn">
            <span class="material-symbols-rounded">add</span>
            Add New Area
          </button>
        </div>
        
        <div class="menu-list" id="areaList">
          <!-- Areas will be loaded here dynamically -->
          <div class="loading">Loading areas...</div>
        </div>
      </div>
    </div>

    <!-- Tables Management Page -->
    <div id="tablesPage" class="page">
      <div class="page-header">
        <h1>Tables Management</h1>
        <p>Create, edit, and manage your restaurant tables</p>
      </div>
      <div class="page-content">
        <div class="menu-actions">
          <button class="btn btn-primary" id="addTableBtn">
            <span class="material-symbols-rounded">add</span>
            Add New Table
          </button>
        </div>
        
        <div class="menu-list" id="tableList">
          <!-- Tables will be loaded here dynamically -->
          <div class="loading">Loading tables...</div>
        </div>
      </div>
    </div>

    <!-- QR Codes Page -->
    <div id="qrCodesPage" class="page">
      <div class="page-header">
        <h1>QR Codes for Tables</h1>
        <p>Generate QR codes for customer menu access</p>
      </div>
      <div class="page-content">
        <div style="margin-bottom: 2rem;">
          <button class="btn btn-primary" onclick="generateAllQRCodes()" style="display: flex; align-items: center; gap: 0.5rem;">
            <span class="material-symbols-rounded">download</span>
            Download All QR Codes
          </button>
        </div>
        
        <div id="qrCodesGrid" class="qr-codes-grid">
          <div class="loading">Loading QR codes...</div>
        </div>
      </div>
    </div>

    <!-- Reservations Management Page -->
    <div id="reservationsPage" class="page">
      <div class="page-header">
        <h1>Reservations</h1>
        <p>View and manage customer reservations</p>
      </div>
      <div class="page-content">
        <div class="page-toolbar reservation-toolbar" style="display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; align-items: center; background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-left: 0; margin-right: 0;">
          <div class="toolbar-left" style="display: flex; gap: 1rem; flex: 1; flex-wrap: wrap; align-items: center; min-width: 0;">
            <div class="search-wrapper" style="flex: 1; min-width: 250px; max-width: 100%; position: relative; border: 2px solid #e5e7eb; border-radius: 10px; padding: 0 0.75rem 0 2.75rem; background: white;">
              <span class="material-symbols-rounded" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 1.2rem; pointer-events: none; z-index: 1;">search</span>
              <input type="text" id="reservationSearch" placeholder="Search by name, phone, email..." style="width: 100%; padding: 0.875rem 0; border: none; border-radius: 0; font-size: 0.95rem; transition: all 0.2s; outline: none; background: transparent;" onfocus="this.parentElement.style.borderColor='#f70000'; this.parentElement.style.boxShadow='0 0 0 3px rgba(247,0,0,0.1)'" onblur="this.parentElement.style.borderColor='#e5e7eb'; this.parentElement.style.boxShadow='none';">
            </div>
            <div class="date-range-wrapper" style="display: flex; gap: 0.75rem; align-items: center; background: #f9fafb; padding: 0.5rem; border-radius: 10px; border: 1px solid #e5e7eb; flex-wrap: wrap; flex: 1; min-width: 0;">
              <div style="position: relative; flex: 1; min-width: 140px;">
                <input type="date" id="reservationDateFrom" style="width: 100%; padding: 0.875rem 2.5rem 0.875rem 1rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem; background: white; cursor: pointer; transition: all 0.2s; outline: none; box-sizing: border-box;" onfocus="this.style.borderColor='#f70000'; this.style.boxShadow='0 0 0 3px rgba(247,0,0,0.1)'" onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none'; this.style.outline='none';" onchange="this.blur();">
                <span class="material-symbols-rounded" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #374151; font-size: 1.2rem; cursor: pointer; z-index: 1; pointer-events: auto; transition: color 0.2s;" onclick="const input = document.getElementById('reservationDateFrom'); input.showPicker(); setTimeout(() => input.blur(), 100);" onmouseover="this.style.color='#111827'" onmouseout="this.style.color='#374151'">calendar_today</span>
              </div>
              <span style="color: #6b7280; font-weight: 600; font-size: 0.9rem; padding: 0 0.25rem; white-space: nowrap;">to</span>
              <div style="position: relative; flex: 1; min-width: 140px;">
                <input type="date" id="reservationDateTo" style="width: 100%; padding: 0.875rem 2.5rem 0.875rem 1rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem; background: white; cursor: pointer; transition: all 0.2s; outline: none; box-sizing: border-box;" onfocus="this.style.borderColor='#f70000'; this.style.boxShadow='0 0 0 3px rgba(247,0,0,0.1)'" onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none'; this.style.outline='none';" onchange="this.blur();">
                <span class="material-symbols-rounded" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #374151; font-size: 1.2rem; cursor: pointer; z-index: 1; pointer-events: auto; transition: color 0.2s;" onclick="const input = document.getElementById('reservationDateTo'); input.showPicker(); setTimeout(() => input.blur(), 100);" onmouseover="this.style.color='#111827'" onmouseout="this.style.color='#374151'">calendar_today</span>
              </div>
              <button onclick="clearDateRange()" style="padding: 0.875rem; background: #ffffff; border: 2px solid #d1d5db; border-radius: 8px; cursor: pointer; color: #374151; display: flex; align-items: center; justify-content: center; transition: all 0.2s; min-width: 42px; height: 42px; outline: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05); flex-shrink: 0;" onmouseover="this.style.background='#f3f4f6'; this.style.borderColor='#9ca3af'; this.style.color='#111827'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#d1d5db'; this.style.color='#374151'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.05)'" onfocus="this.style.outline='none';" onblur="this.style.outline='none';" title="Clear date range">
                <span class="material-symbols-rounded" style="font-size: 1.3rem; font-weight: 500;">close</span>
              </button>
            </div>
          </div>
          <div class="toolbar-right" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; min-width: 0; width: 100%;">
            <select id="reservationStatusFilter" class="filter-select" style="flex: 1; min-width: 160px; max-width: 100%; padding: 0.875rem 2.5rem 0.875rem 1rem; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 0.95rem; background: white; cursor: pointer; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%236b7280\' stroke-width=\'2\'><polyline points=\'6 9 12 15 18 9\'></polyline></svg>'); background-repeat: no-repeat; background-position: right 0.75rem center; transition: all 0.2s; outline: none; box-sizing: border-box;" onfocus="this.style.borderColor='#f70000'; this.style.boxShadow='0 0 0 3px rgba(247,0,0,0.1)'" onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none'; this.style.outline='none';" onchange="this.blur();">
              <option value="">All Status</option>
              <option value="Pending">Pending</option>
              <option value="Confirmed">Confirmed</option>
              <option value="Checked In">Checked In</option>
              <option value="Completed">Completed</option>
              <option value="Cancelled">Cancelled</option>
              <option value="No Show">No Show</option>
            </select>
            <button class="btn btn-primary" id="addReservationBtn" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.875rem 1rem; background: #f70000 !important; color: #ffffff !important; border: 2px solid #f70000 !important; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 1rem; transition: all 0.2s; box-shadow: 0 2px 4px rgba(247,0,0,0.3); letter-spacing: 0.3px; white-space: nowrap; flex-shrink: 0; width: auto; justify-content: center;" onmouseover="this.style.background='#d60000'; this.style.borderColor='#d60000'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(247,0,0,0.4)';" onmouseout="this.style.background='#f70000'; this.style.borderColor='#f70000'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(247,0,0,0.3)';">
              <span class="material-symbols-rounded" style="font-size: 1.3rem; font-weight: 600; color: #ffffff !important;">add_circle</span>
              <span style="color: #ffffff !important; font-weight: 700;">Add Reservation</span>
            </button>
          </div>
        </div>
        
        <div class="menu-list" id="reservationList" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 350px), 1fr)); gap: 1.5rem; max-width: 100%;">
          <!-- Reservations will be loaded here dynamically -->
          <div class="loading">Loading reservations...</div>
        </div>
      </div>
    </div>

    <!-- POS Page -->
    <div id="posPage" class="page">
      <div class="page-header">
        <h1>Point of Sale</h1>
        <p>Process orders and manage transactions</p>
      </div>
      <div class="page-content pos-content">
        <div class="pos-container">
          <!-- Left Side - Menu Items -->
          <div class="pos-menu-section">
            <div class="pos-filters">
              <select id="posMenuFilter" class="filter-select">
                <option value="">All Menus</option>
              </select>
              <select id="posCategoryFilter" class="filter-select">
                <option value="">All Categories</option>
              </select>
              <select id="posTypeFilter" class="filter-select">
                <option value="">All Types</option>
                <option value="Veg">Veg</option>
                <option value="Non Veg">Non Veg</option>
                <option value="Egg">Egg</option>
                <option value="Drink">Drink</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="pos-menu-items" id="posMenuItems">
              <!-- Menu items will be loaded here -->
              <div class="loading">Loading menu items...</div>
            </div>
          </div>
          
          <!-- Mobile Sticky Add Item Button -->
          <button id="mobileAddItemBtn" class="mobile-add-item-btn" onclick="openMobileAddItemModal()" style="display: none;">
            <span class="material-symbols-rounded">add</span>
            <span>Add Item</span>
          </button>

          <!-- Right Side - Cart -->
          <div class="pos-cart-section">
            <div class="pos-cart-header">
              <h3>Order Cart</h3>
              <button class="btn-clear-cart" id="clearCartBtn">
                <span class="material-symbols-rounded">delete</span>
                Clear Cart
              </button>
            </div>
            
            <div class="pos-table-select">
              <label for="selectPosTable">Select Table:</label>
              <select id="selectPosTable" class="filter-select">
                <option value="">Walk-in</option>
              </select>
            </div>

            <div class="pos-cart-items" id="posCartItems">
              <div class="empty-cart">
                <span class="material-symbols-rounded">shopping_cart</span>
                <p>Cart is empty</p>
                <p class="empty-subtext">Add items from the menu</p>
              </div>
            </div>

            <div class="pos-cart-summary">
              <div class="cart-summary-row">
                <span>Subtotal:</span>
                <span id="cartSubtotal"><?php echo htmlspecialchars($currency_symbol); ?>0.00</span>
              </div>
              <div class="cart-summary-row">
                <span>CGST (2.5%):</span>
                <span id="cartCGST"><?php echo htmlspecialchars($currency_symbol); ?>0.00</span>
              </div>
              <div class="cart-summary-row">
                <span>SGST (2.5%):</span>
                <span id="cartSGST"><?php echo htmlspecialchars($currency_symbol); ?>0.00</span>
              </div>
              <div class="cart-summary-row">
                <span>GST (5%):</span>
                <span id="cartTax"><?php echo htmlspecialchars($currency_symbol); ?>0.00</span>
              </div>
              <div class="cart-summary-row total">
                <span>Total:</span>
                <span id="cartTotal"><?php echo htmlspecialchars($currency_symbol); ?>0.00</span>
              </div>
            </div>

            <div class="pos-cart-actions">
              <button class="btn btn-secondary" id="holdOrderBtn">
                <span class="material-symbols-rounded">pause</span>
                Hold Order
              </button>
              <button class="btn btn-primary" id="processPaymentBtn">
                <span class="material-symbols-rounded">payment</span>
                Process Payment
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Mobile Bill Summary (Above Buttons) -->
      <div id="mobilePosBillSummary" class="mobile-pos-bill-summary" style="display: none;">
        <div class="mobile-bill-summary-card">
          <div class="mobile-bill-summary-header" onclick="toggleMobileBillDetails()">
            <div>
              <div style="font-size:0.75rem;color:#6b7280;margin-bottom:0.125rem;">Bill Summary</div>
              <div style="font-size:1.1rem;font-weight:700;color:#111827;">
                Total: <span id="mobilePosBillTotal"><?php echo htmlspecialchars($currency_symbol); ?>0.00</span>
              </div>
            </div>
            <span class="material-symbols-rounded" id="mobilePosBillSummaryArrow" style="font-size:1.25rem;color:#6b7280;transition:transform 0.3s;">chevron_right</span>
          </div>
          <div id="mobilePosBillDetails" style="display:none;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #e5e7eb;">
            <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;font-size:0.85rem;">
              <span style="color:#6b7280;">Subtotal:</span>
              <span style="font-weight:600;color:#111827;" id="mobilePosBillSubtotal"><?php echo htmlspecialchars($currency_symbol); ?>0.00</span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;font-size:0.85rem;">
              <span style="color:#6b7280;">CGST (2.5%):</span>
              <span style="font-weight:600;color:#111827;" id="mobilePosBillCGST"><?php echo htmlspecialchars($currency_symbol); ?>0.00</span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;font-size:0.85rem;">
              <span style="color:#6b7280;">SGST (2.5%):</span>
              <span style="font-weight:600;color:#111827;" id="mobilePosBillSGST"><?php echo htmlspecialchars($currency_symbol); ?>0.00</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.85rem;">
              <span style="color:#6b7280;">Tax Total:</span>
              <span style="font-weight:600;color:#111827;" id="mobilePosBillTax"><?php echo htmlspecialchars($currency_symbol); ?>0.00</span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Mobile Sticky Bottom Buttons -->
      <div id="mobilePosBottomActions" class="mobile-pos-bottom-actions" style="display: none;">
        <button class="mobile-pos-btn mobile-pos-btn-hold" id="mobileHoldOrderBtn">
          <span class="material-symbols-rounded">pause</span>
          <span>Save & Hold</span>
        </button>
        <button class="mobile-pos-btn mobile-pos-btn-bill" id="mobileProcessPaymentBtn">
          <span class="material-symbols-rounded">payment</span>
          <span>Save & Bill</span>
        </button>
      </div>
    </div>

    <!-- KOT Page -->
    <div id="kotPage" class="page">
      <div class="page-header">
        <div>
          <h1>Kitchen Order Ticket (KOT)</h1>
          <p>View and manage kitchen orders</p>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
          <span id="kotLastRefresh" style="color: #6b7280; font-size: 0.875rem;">Auto-refreshing every 5 seconds...</span>
          <button class="btn btn-primary" onclick="loadKOTOrders()">
            <span class="material-symbols-rounded" style="vertical-align: middle;">refresh</span> Refresh
          </button>
        </div>
      </div>
      <div class="page-content">
        <div class="kot-filters">
          <select id="kotStatusFilter" class="filter-select" onchange="loadKOTOrders()">
            <option value="">All Status</option>
            <option value="Pending">Pending</option>
            <option value="Preparing">Preparing</option>
            <option value="Ready">Ready</option>
          </select>
          <select id="kotTableFilter" class="filter-select" onchange="loadKOTOrders()">
            <option value="">All Tables</option>
          </select>
        </div>
        <div class="kot-list" id="kotList">
          <!-- KOT orders will be loaded here -->
          <div class="loading">Loading KOT orders...</div>
        </div>
      </div>
    </div>

    <!-- Orders Page -->
    <div id="ordersPage" class="page">
      <div class="page-header">
        <h1>Orders Management</h1>
        <p>View and manage all orders</p>
      </div>
      <div class="page-content">
        <div class="orders-filters" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
          <div style="position: relative; flex: 1; min-width: 250px;">
            <span class="material-symbols-rounded" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #666; font-size: 1.2rem; pointer-events: none;">search</span>
            <input type="text" id="ordersSearch" placeholder="Search by order number, customer name, or table..." style="width: 100%; padding: 0.75rem 0.75rem 0.75rem 2.5rem; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9rem;">
          </div>
          <select id="ordersStatusFilter" class="filter-select">
            <option value="">All Status</option>
            <option value="Pending">Pending</option>
            <option value="Preparing">Preparing</option>
            <option value="Ready">Ready</option>
            <option value="Served">Served</option>
            <option value="Completed">Completed</option>
            <option value="Cancelled">Cancelled</option>
          </select>
          <select id="ordersPaymentFilter" class="filter-select">
            <option value="">All Payment Status</option>
            <option value="Pending">Pending</option>
            <option value="Paid">Paid</option>
            <option value="Partially Paid">Partially Paid</option>
            <option value="Refunded">Refunded</option>
          </select>
          <select id="ordersTypeFilter" class="filter-select">
            <option value="">All Order Types</option>
            <option value="Dine-in">Dine-in</option>
            <option value="Takeaway">Takeaway</option>
            <option value="Delivery">Delivery</option>
          </select>
          <input type="date" id="ordersDateFilter" class="filter-select" style="padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9rem;">
          <button onclick="exportOrdersToCSV()" style="padding: 0.75rem 1.5rem; background: #28a745; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
            <span class="material-symbols-rounded" style="font-size: 1rem;">download</span>
            Export CSV
          </button>
        </div>
        <div class="orders-list" id="ordersList">
          <!-- Orders will be loaded here -->
          <div class="loading">Loading orders...</div>
        </div>
      </div>
    </div>

    <!-- Customers Page -->
    <div id="customersPage" class="page">
      <div class="page-header">
        <h1>Customers</h1>
        <p>View and manage your customers</p>
      </div>
      <div class="page-content">
        <div class="page-toolbar">
          <div class="toolbar-left">
            <div class="search-wrapper">
              <span class="material-symbols-rounded">search</span>
              <input type="text" id="customerSearch" placeholder="Search customers...">
            </div>
          </div>
          <div class="toolbar-right">
            <button onclick="exportCustomersToCSV()" style="padding: 0.75rem 1.5rem; background: #28a745; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
              <span class="material-symbols-rounded">download</span>
              Export CSV
            </button>
          </div>
            
            <select id="customerSortBy" class="filter-select">
              <option value="name">Sort by Name</option>
              <option value="visits">Most Visits</option>
              <option value="recent">Most Recent</option>
            </select>
          </div>
          
          <button class="btn btn-primary" id="addCustomerBtn">
            <span class="material-symbols-rounded">add</span>
            Add Customer
          </button>
        </div>
        
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>Avatar</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Address</th>
                <th>Total Visits</th>
                <th>Total Spent</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="customerList">
              <tr>
                <td colspan="8" class="loading">Loading customers...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Staff Page -->
    <div id="staffPage" class="page">
      <div class="page-header">
        <h1>Staff Management</h1>
        <p>Manage your restaurant staff</p>
      </div>
      <div class="page-content">
        <div class="page-toolbar">
          <div class="toolbar-left">
            <div class="search-wrapper">
              <span class="material-symbols-rounded">search</span>
              <input type="text" id="staffSearch" placeholder="Search staff...">
            </div>
          </div>
          <div class="toolbar-right">
            <button onclick="exportStaffToCSV()" style="padding: 0.75rem 1.5rem; background: #28a745; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
              <span class="material-symbols-rounded">download</span>
              Export CSV
            </button>
          </div>
            
            <select id="staffSortBy" class="filter-select">
              <option value="name">Sort by Name</option>
              <option value="role">Sort by Role</option>
            </select>
          </div>
          
          <button class="btn btn-primary" id="addStaffBtn">
            <span class="material-symbols-rounded">add</span>
            Add Staff
          </button>
        </div>
        
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>Avatar</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="staffList">
              <tr>
                <td colspan="6" class="loading">Loading staff...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Waiter Requests Page -->
    <div id="waiterRequestsPage" class="page">
      <div class="page-header">
        <h1>Waiter Requests</h1>
        <p>Manage service requests from tables</p>
      </div>
      <div class="page-content">
        <div id="waiterRequestsList">
          <!-- Waiter requests will be loaded here dynamically grouped by area -->
          <div class="loading">Loading waiter requests...</div>
        </div>
      </div>
    </div>
  </main>

  <!-- Menu Modal (Add/Edit) -->
  <div id="menuModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="modalTitle">Add New Category</h2>
        <span class="close">&times;</span>
      </div>
      <div class="modal-body">
        <form id="menuForm" enctype="multipart/form-data">
          <input type="hidden" id="menuId" name="menuId" value="">
          <div class="form-group">
            <label for="menuName">Category Name:</label>
            <input type="text" id="menuName" name="menuName" required placeholder="Enter category name (e.g., Breakfast, Lunch, Dinner)">
          </div>
          <div class="form-group">
            <label for="menuImage">Category Image:</label>
            <div class="file-upload">
              <input type="file" id="menuImage" name="menuImage" accept="image/*">
              <label for="menuImage" class="file-upload-btn">
                <span class="material-symbols-rounded">upload</span>
                Choose File
              </label>
              <span class="file-name" id="menuImageFileName">No file chosen</span>
            </div>
            
            <!-- Image Cropper Section for Category -->
            <div id="menuImageCropperSection" style="display: none; margin-top: 1rem;">
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1rem;">
                <!-- Cropper Container -->
                <div>
                  <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151;">Crop Image:</label>
                  <div style="max-width: 100%; height: 300px; background: #f3f4f6; border-radius: 8px; overflow: hidden;">
                    <img id="menuImageToCrop" src="" alt="Image to crop" style="max-width: 100%; max-height: 100%; display: block;">
                  </div>
                  <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <button type="button" id="cropMenuImageBtn" class="btn btn-primary" style="flex: 1;">
                      <span class="material-symbols-rounded" style="font-size: 1.2rem; vertical-align: middle;">crop</span>
                      Apply Crop
                    </button>
                    <button type="button" id="resetMenuCropBtn" class="btn btn-secondary" style="flex: 1;">
                      <span class="material-symbols-rounded" style="font-size: 1.2rem; vertical-align: middle;">refresh</span>
                      Reset
                    </button>
                  </div>
                </div>
                
                <!-- Category Preview -->
                <div>
                  <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151;">Preview:</label>
                  <div style="border: 2px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div id="menuCategoryPreviewImage" style="width: 100%; height: 200px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                      <img id="croppedMenuPreviewImg" src="" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                      <span style="color: white; font-size: 3rem; opacity: 0.5;">📁</span>
                    </div>
                    <div style="padding: 1rem; background: #f9fafb;">
                      <div style="font-weight: 600; color: #111827; margin-bottom: 0.25rem;" id="previewCategoryName">Category Name</div>
                      <div style="font-size: 0.875rem; color: #6b7280;">Category preview</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Old Preview (hidden, kept for backward compatibility) -->
            <div id="menuImagePreview" style="margin-top: 10px; display: none;">
              <img id="menuImagePreviewImg" src="" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 2px solid #e5e7eb;">
            </div>
            <input type="hidden" id="menuImageBase64" name="menuImageBase64" value="">
          </div>
          <div class="form-actions">
            <button type="button" class="btn btn-cancel" id="cancelBtn">Cancel</button>
            <button type="submit" class="btn btn-save" id="saveBtn">Save Category</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Menu Item Modal (Add/Edit) -->
  <div id="menuItemModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="menuItemModalTitle">Add New Menu Item</h2>
        <span class="close">&times;</span>
      </div>
      <div class="modal-body">
        <form id="menuItemForm" enctype="multipart/form-data">
          <input type="hidden" id="menuItemId" name="menuItemId" value="">
          <input type="hidden" id="itemImageBase64" name="itemImageBase64" value="">
          
          <div class="form-group">
            <label for="itemNameEn">Item Name (English):</label>
            <input type="text" id="itemNameEn" name="itemNameEn" required placeholder="e.g., Margherita Pizza">
          </div>
          
          <div class="form-group">
            <label for="itemDescriptionEn">Item Description (English):</label>
            <textarea id="itemDescriptionEn" name="itemDescriptionEn" rows="3" placeholder="e.g., A classic Italian pizza with fresh tomatoes and basil."></textarea>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="chooseMenu">Choose Menu:</label>
              <select id="chooseMenu" name="chooseMenu" required>
                <option value="">--</option>
              </select>
            </div>
            
            <div class="form-group">
              <label for="itemCategory">Category:</label>
              <select id="itemCategory" name="itemCategory">
                <option value="">--</option>
                <option value="Main Course">Main Course</option>
                <option value="Appetizer">Appetizer</option>
                <option value="Dessert">Dessert</option>
                <option value="Snacks">Snacks</option>
                <option value="Beverages">Beverages</option>
                <option value="Salad">Salad</option>
                <option value="Soup">Soup</option>
              </select>
            </div>
          </div>
          
          <div class="form-group">
            <label>Item Type:</label>
            <div class="type-buttons">
              <button type="button" class="type-btn active" data-type="Veg">
                <span class="material-symbols-rounded">eco</span>
                Veg
              </button>
              <button type="button" class="type-btn" data-type="Non Veg">
                <span class="material-symbols-rounded">restaurant</span>
                Non Veg
              </button>
              <button type="button" class="type-btn" data-type="Egg">
                <span class="material-symbols-rounded">egg</span>
                Egg
              </button>
              <button type="button" class="type-btn" data-type="Drink">
                <span class="material-symbols-rounded">local_bar</span>
                Drink
              </button>
              <button type="button" class="type-btn" data-type="Other">
                <span class="material-symbols-rounded">inventory_2</span>
                Other
              </button>
            </div>
            <input type="hidden" id="itemType" name="itemType" value="Veg">
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="preparationTime">Preparation Time:</label>
              <input type="number" id="preparationTime" name="preparationTime" min="0" value="0" placeholder="0">
              <span class="input-suffix">Minutes</span>
            </div>
            
            <div class="form-group">
              <label for="isAvailable">Is Available:</label>
              <select id="isAvailable" name="isAvailable">
                <option value="1">Yes</option>
                <option value="0">No</option>
              </select>
            </div>
          </div>
          
          <div class="form-group">
            <label for="itemImage">Item Image:</label>
            <div class="file-upload">
              <input type="file" id="itemImage" name="itemImage" accept="image/*">
              <label for="itemImage" class="file-upload-btn">
                <span class="material-symbols-rounded">upload</span>
                Choose File
              </label>
              <span class="file-name">No file chosen</span>
            </div>
            
            <!-- Image Cropper Section -->
            <div id="imageCropperSection" style="display: none; margin-top: 1rem;">
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1rem;">
                <!-- Cropper Container -->
                <div>
                  <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151;">Crop Image:</label>
                  <div style="max-width: 100%; height: 300px; background: #f3f4f6; border-radius: 8px; overflow: hidden;">
                    <img id="imageToCrop" src="" alt="Image to crop" style="max-width: 100%; max-height: 100%; display: block;">
                  </div>
                  <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <button type="button" id="cropImageBtn" class="btn btn-primary" style="flex: 1;">
                      <span class="material-symbols-rounded" style="font-size: 1.2rem; vertical-align: middle;">crop</span>
                      Apply Crop
                    </button>
                    <button type="button" id="resetCropBtn" class="btn btn-secondary" style="flex: 1;">
                      <span class="material-symbols-rounded" style="font-size: 1.2rem; vertical-align: middle;">refresh</span>
                      Reset
                    </button>
                  </div>
                </div>
                
                <!-- Website Preview -->
                <div>
                  <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151;">Preview on Website:</label>
                  <div style="border: 2px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div id="websitePreviewImage" style="width: 100%; height: 200px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                      <img id="croppedPreviewImg" src="" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                      <span style="color: white; font-size: 3rem; opacity: 0.5;">🍽️</span>
                    </div>
                    <div style="padding: 1rem; background: #f9fafb;">
                      <div style="font-weight: 600; color: #111827; margin-bottom: 0.25rem;" id="previewItemName">Item Name</div>
                      <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;">Item Description</div>
                      <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 700; color: #f70000; font-size: 1.125rem;">₹0.00</span>
                        <button style="background: #f70000; color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer;">
                          <span class="material-symbols-rounded" style="font-size: 1rem; vertical-align: middle;">add_shopping_cart</span>
                          Add
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Old Preview (hidden, kept for backward compatibility) -->
            <div id="imagePreview" class="image-preview" style="display: none;">
              <img id="previewImg" src="" alt="Preview">
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="basePrice">Price:</label>
              <div class="price-input">
                <span class="currency-symbol" id="currencySymbolDisplay"><?php echo htmlspecialchars($currency_symbol); ?></span>
                <input type="number" id="basePrice" name="basePrice" min="0" step="0.01" value="0.00" placeholder="0.00">
              </div>
            </div>
            
            <div class="form-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" id="hasVariations" name="hasVariations" onchange="toggleVariationsSection()">
                <span class="checkmark"></span>
                Has Variations (e.g., Small, Medium, Large)
              </label>
            </div>
          </div>
          
          <!-- Variations Section -->
          <div id="variationsSection" style="display: none; margin-top: 1.5rem; padding: 1.5rem; background: #f9fafb; border-radius: 8px; border: 2px solid #e5e7eb;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
              <h3 style="margin: 0; font-size: 1.1rem; color: #111827; display: flex; align-items: center; gap: 0.5rem;">
                <span class="material-symbols-rounded" style="font-size: 1.2rem;">tune</span>
                Item Variations
              </h3>
              <button type="button" onclick="addVariationRow()" style="padding: 0.5rem 1rem; background: #f70000; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                <span class="material-symbols-rounded" style="font-size: 1rem;">add</span>
                Add Variation
              </button>
            </div>
            <p style="margin: 0 0 1rem 0; color: #6b7280; font-size: 0.875rem;">Add different sizes or options with their prices (e.g., Small: ₹100, Medium: ₹150, Large: ₹200)</p>
            <div id="variationsList" style="display: flex; flex-direction: column; gap: 0.75rem;">
              <!-- Variations will be added here dynamically -->
            </div>
            <div id="noVariationsMessage" style="text-align: center; padding: 2rem; color: #9ca3af; font-size: 0.9rem;">
              No variations added yet. Click "Add Variation" to add size options.
            </div>
          </div>
          
          <div class="form-actions">
            <button type="button" class="btn btn-cancel" id="menuItemCancelBtn">Cancel</button>
            <button type="submit" class="btn btn-save" id="menuItemSaveBtn">Save Menu Item</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Confirm Delete</h2>
        <span class="close">&times;</span>
      </div>
      <div class="modal-body">
        <p id="deleteMessage">Are you sure you want to delete this item? This action cannot be undone.</p>
        <div class="form-actions">
          <button type="button" class="btn btn-cancel" id="deleteCancelBtn">Cancel</button>
          <button type="button" class="btn btn-delete" id="deleteConfirmBtn">Delete</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Area Modal (Add/Edit) -->
  <div id="areaModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="areaModalTitle">Add New Area</h2>
        <span class="close">&times;</span>
      </div>
      <div class="modal-body">
        <form id="areaForm">
          <input type="hidden" id="areaId" name="areaId" value="">
          <div class="form-group">
            <label for="areaName">Area Name:</label>
            <input type="text" id="areaName" name="areaName" required placeholder="Enter area name (e.g., Indoor, Outdoor, Smoking)">
          </div>
          <div class="form-actions">
            <button type="button" class="btn btn-cancel" id="areaCancelBtn">Cancel</button>
            <button type="submit" class="btn btn-save" id="areaSaveBtn">Save Area</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Table Modal (Add/Edit) -->
  <div id="tableModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="tableModalTitle">Add New Table</h2>
        <span class="close">&times;</span>
      </div>
      <div class="modal-body">
        <form id="tableForm">
          <input type="hidden" id="tableId" name="tableId" value="">
          <div class="form-group">
            <label for="tableNumber">Table Number:</label>
            <input type="text" id="tableNumber" name="tableNumber" required placeholder="Enter table number">
          </div>
          <div class="form-group">
            <label for="capacity">Capacity:</label>
            <input type="number" id="capacity" name="capacity" min="1" value="4" required placeholder="Number of seats">
          </div>
          <div class="form-group">
            <label for="chooseArea">Area:</label>
            <select id="chooseArea" name="chooseArea" required>
              <option value="">--</option>
            </select>
          </div>
          <div class="form-actions">
            <button type="button" class="btn btn-cancel" id="tableCancelBtn">Cancel</button>
            <button type="submit" class="btn btn-save" id="tableSaveBtn">Save Table</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Reservation Modal (Add/Edit) -->
  <div id="reservationModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="reservationModalTitle">New Reservation</h2>
        <span class="close">&times;</span>
      </div>
      <div class="modal-body">
        <div id="reservationFormErrors" style="display: none; background: #fee; border: 2px solid #fcc; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; color: #c33;">
          <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
            <span class="material-symbols-rounded" style="color: #c33;">error</span>
            <strong>Please fix the following errors:</strong>
          </div>
          <ul id="reservationErrorList" style="margin: 0; padding-left: 1.5rem; color: #c33;"></ul>
        </div>
        <form id="reservationForm">
          <input type="hidden" id="reservationId" name="reservationId" value="">
          <div class="form-row">
            <div class="form-group">
              <label for="reservationDate">Date: <span style="color: red;">*</span></label>
              <input type="date" id="reservationDate" name="reservationDate" required>
              <span class="field-error" id="reservationDateError" style="display: none; color: #c33; font-size: 0.875rem; margin-top: 0.25rem;"></span>
            </div>
            <div class="form-group">
              <label for="noOfGuests">Guests: <span style="color: red;">*</span></label>
              <input type="number" id="noOfGuests" name="noOfGuests" min="1" value="1" required placeholder="Number of guests">
              <span class="field-error" id="noOfGuestsError" style="display: none; color: #c33; font-size: 0.875rem; margin-top: 0.25rem;"></span>
            </div>
          </div>
          <div class="form-group">
            <label for="mealType">Meal Type: <span style="color: red;">*</span></label>
            <select id="mealType" name="mealType" required>
              <option value="Breakfast">Breakfast</option>
              <option value="Lunch" selected>Lunch</option>
              <option value="Dinner">Dinner</option>
              <option value="Snacks">Snacks</option>
            </select>
            <span class="field-error" id="mealTypeError" style="display: none; color: #c33; font-size: 0.875rem; margin-top: 0.25rem;"></span>
          </div>
          <div class="form-group">
            <label for="timeSlot">Select Time Slot: <span style="color: red;">*</span></label>
            <div id="timeSlots" class="time-slots">
              <!-- Time slots will be added dynamically -->
            </div>
            <div id="customTimeSlotContainer" style="display: none; margin-top: 1rem;">
              <label for="customTimeSlot" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151;">Enter Custom Time:</label>
              <input type="time" id="customTimeSlot" style="width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 1rem; transition: all 0.2s;" onfocus="this.style.borderColor='#f70000'; this.style.boxShadow='0 0 0 3px rgba(247,0,0,0.1)'" onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none';">
              <span style="display: block; margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">Or enter time in 12-hour format (e.g., 2:30 PM)</span>
            </div>
            <span class="field-error" id="timeSlotError" style="display: none; color: #c33; font-size: 0.875rem; margin-top: 0.25rem;"></span>
          </div>
          <div class="form-group">
            <label for="specialRequest">Any special request?</label>
            <textarea id="specialRequest" name="specialRequest" rows="3" placeholder="Enter any special requests..."></textarea>
            <span class="field-error" id="specialRequestError" style="display: none; color: #c33; font-size: 0.875rem; margin-top: 0.25rem;"></span>
          </div>
          <div class="form-group">
            <label for="customerName">Customer Name: <span style="color: red;">*</span></label>
            <input type="text" id="customerName" name="customerName" required placeholder="Enter customer name" autocomplete="off">
            <span class="field-error" id="customerNameError" style="display: none; color: #c33; font-size: 0.875rem; margin-top: 0.25rem;"></span>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="phone">Phone:</label>
              <input type="tel" id="phone" name="phone" placeholder="Enter phone number (optional)" autocomplete="off">
              <span class="field-error" id="phoneError" style="display: none; color: #c33; font-size: 0.875rem; margin-top: 0.25rem;"></span>
            </div>
            <div class="form-group">
              <label for="email">Email Address:</label>
              <input type="email" id="email" name="email" placeholder="Enter email address (optional)">
              <span class="field-error" id="emailError" style="display: none; color: #c33; font-size: 0.875rem; margin-top: 0.25rem;"></span>
            </div>
          </div>
          <div class="form-group">
            <label for="selectTable">Assign Table:</label>
            <select id="selectTable" name="selectTable">
              <option value="">-- Select Table --</option>
            </select>
            <span class="field-error" id="selectTableError" style="display: none; color: #c33; font-size: 0.875rem; margin-top: 0.25rem;"></span>
          </div>
          <div class="form-actions">
            <button type="button" class="btn btn-cancel" id="reservationCancelBtn">Cancel</button>
            <button type="submit" class="btn btn-save" id="reservationSaveBtn">Reserve Now</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Customer Modal (Add/Edit) -->
  <div id="customerModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="customerModalTitle">Add New Customer</h2>
        <span class="close">&times;</span>
      </div>
      <div class="modal-body">
        <form id="customerForm">
          <input type="hidden" id="customerId" name="customerId" value="">
          <div class="form-group">
            <label for="customerNameInput">Customer Name:</label>
            <input type="text" id="customerNameInput" name="customerName" required placeholder="Enter customer name" autocomplete="off">
          </div>
          <div class="form-group">
            <label for="customerPhoneInput">Phone:</label>
            <input type="tel" id="customerPhoneInput" name="phone" placeholder="Enter phone number (optional)" autocomplete="off">
          </div>
          <div class="form-group">
            <label for="customerEmailInput">Email Address:</label>
            <input type="email" id="customerEmailInput" name="email" placeholder="Enter email address">
          </div>
          <div class="form-actions">
            <button type="button" class="btn btn-cancel" id="customerCancelBtn">Cancel</button>
            <button type="submit" class="btn btn-save" id="customerSaveBtn">Save Customer</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Staff Modal (Add Member) -->
  <div id="staffModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Add Member</h2>
        <span class="close">&times;</span>
      </div>
      <div class="modal-body">
        <form id="staffForm">
          <input type="hidden" id="staffId" name="staffId" value="">
          <div class="form-group">
            <label for="memberName">Member Name:</label>
            <input type="text" id="memberName" name="memberName" required placeholder="admin@example.com">
          </div>
          <div class="form-group">
            <label for="memberEmail">Email Address:</label>
            <input type="email" id="memberEmail" name="memberEmail" required placeholder="Enter email address">
          </div>
          <div class="form-group">
            <label for="staffPhone">Restaurant Phone Number:</label>
            <div class="form-row">
              <select id="countryCode" name="countryCode" style="width: 30%;">
                <option value="+1">+1</option>
                <option value="+91">+91</option>
                <option value="+44">+44</option>
                <option value="+61">+61</option>
              </select>
              <input type="tel" id="staffPhone" name="restaurantPhone" required placeholder="1234567890" style="width: 68%;">
            </div>
          </div>
          <div class="form-group">
            <label for="memberPassword">Password:</label>
            <input type="password" id="memberPassword" name="memberPassword" required placeholder="Enter password">
          </div>
          <div class="form-group">
            <label for="memberRole">Role:</label>
            <select id="memberRole" name="memberRole" required>
              <option value="Admin" selected>Admin</option>
              <option value="Manager">Manager</option>
              <option value="Waiter">Waiter</option>
              <option value="Chef">Chef</option>
            </select>
          </div>
          <div class="form-actions">
            <button type="button" class="btn btn-cancel" id="staffCancelBtn">Cancel</button>
            <button type="submit" class="btn btn-save" id="staffSaveBtn">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Renewal Required Modal -->
  <div id="renewalModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;" onclick="if(event.target===this) this.style.display='none';">
    <div style="background:#fff;border-radius:16px;padding:2rem;max-width:500px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3);margin:1rem;">
      <div style="font-size:4rem;margin-bottom:1rem;">⚠️</div>
      <h2 style="margin:0 0 1rem;color:#111827;font-size:1.5rem;">Subscription Required</h2>
      <p style="color:#6b7280;margin:0 0 1.5rem;line-height:1.6;">Your trial has expired or account is disabled. You cannot use the service at this time.</p>
      <div style="background:#f3f4f6;border-radius:12px;padding:1.5rem;margin:1.5rem 0;border-left:4px solid #dc2626;">
        <p style="color:#111827;font-weight:600;margin:0 0 0.5rem;font-size:1rem;">To renew your subscription, please contact us:</p>
        <p style="color:#6b7280;margin:0;line-height:1.6;font-size:0.95rem;">We'll help you activate your subscription and continue using the service.</p>
      </div>
      <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
        <button onclick="document.getElementById('renewalModal').style.display='none';" style="padding:12px 24px;background:#6b7280;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;transition:opacity 0.2s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Close</button>
      </div>
      <p style="color:#9ca3af;font-size:0.875rem;margin-top:1rem;margin-bottom:0;">Payment integration will be available soon</p>
    </div>
  </div>

  <!-- POS Clear Cart Confirmation Modal -->
  <div id="posClearCartModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:450px;">
      <div class="modal-header">
        <h2>
          <span class="material-symbols-rounded" style="vertical-align:middle;margin-right:0.5rem;">delete_outline</span>
          Clear Cart
        </h2>
        <span class="close" onclick="closePOSClearCartModal()">&times;</span>
      </div>
      <div class="modal-body">
        <div style="text-align:center;padding:1rem 0;">
          <span class="material-symbols-rounded" style="font-size:4rem;color:#f59e0b;display:block;margin-bottom:1rem;">shopping_cart_off</span>
          <p style="font-size:1.1rem;color:#1f2937;margin-bottom:0.5rem;font-weight:600;">Are you sure you want to clear the cart?</p>
          <p style="color:#6b7280;font-size:0.9rem;">This will remove all items from your cart. This action cannot be undone.</p>
        </div>
        <div class="form-actions" style="justify-content:center;margin-top:1.5rem;">
          <button type="button" class="btn btn-cancel" onclick="closePOSClearCartModal()">
            <span class="material-symbols-rounded">close</span>
            Cancel
          </button>
          <button type="button" class="btn btn-delete" id="posClearCartConfirmBtn">
            <span class="material-symbols-rounded">delete</span>
            Clear Cart
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Mobile Add Item Modal -->
  <div id="mobileAddItemModal" class="modal" style="display:none;z-index:10000;" onclick="if(event.target===this) closeMobileAddItemModal();">
    <div class="modal-content" style="max-width:100%;height:85vh;margin:7.5vh auto;display:flex;flex-direction:column;background:white;border-radius:16px 16px 0 0;" onclick="event.stopPropagation();">
      <div class="modal-header" style="flex-shrink:0;border-bottom:2px solid #f3f4f6;padding:0.75rem 1rem;">
        <h2 style="font-size:1.1rem;margin:0;">
          <span class="material-symbols-rounded" style="vertical-align:middle;margin-right:0.5rem;font-size:1.2rem;">add_circle</span>
          Add Item
        </h2>
        <span class="close" onclick="closeMobileAddItemModal()" style="font-size:1.4rem;">&times;</span>
      </div>
      <div class="modal-body" style="flex:1;overflow-y:auto;padding:0.75rem;max-height:calc(85vh - 80px);">
        <div style="margin-bottom:0.75rem;position:relative;">
          <input type="text" id="mobileItemSearch" placeholder="🔍 Search items..." style="width:100%;padding:0.6rem 2.5rem 0.6rem 0.6rem;border:2px solid #e5e7eb;border-radius:8px;font-size:0.85rem;box-sizing:border-box;" oninput="filterMobileItems()">
          <button type="button" onclick="filterMobileItems()" style="position:absolute;right:0.4rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:0.4rem;display:flex;align-items:center;justify-content:center;color:#6b7280;transition:color 0.2s;" onmouseover="this.style.color='#f70000'" onmouseout="this.style.color='#6b7280'">
            <span class="material-symbols-rounded" style="font-size:1.3rem;">search</span>
          </button>
        </div>
        <div id="mobileItemsList" style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.5rem;">
          <!-- Items will be loaded here -->
        </div>
      </div>
    </div>
  </div>

  <!-- POS Variation Selection Modal -->
  <div id="posVariationModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:500px;">
      <div class="modal-header">
        <h2>
          <span class="material-symbols-rounded" style="vertical-align:middle;margin-right:0.5rem;">tune</span>
          Select Variation
        </h2>
        <span class="close" onclick="closePOSVariationModal()">&times;</span>
      </div>
      <div class="modal-body">
        <p style="color:#6b7280;margin-bottom:1.5rem;text-align:center;" id="posVariationItemName">Choose a size or option:</p>
        <div id="posVariationOptions" style="display:flex;flex-direction:column;gap:0.75rem;margin-bottom:1.5rem;">
          <!-- Variations will be added here -->
        </div>
        <div class="form-actions" style="justify-content:center;">
          <button type="button" class="btn btn-cancel" onclick="closePOSVariationModal()">
            <span class="material-symbols-rounded">close</span>
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- POS Payment Method Selection Modal -->
  <div id="posPaymentMethodModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:500px;">
      <div class="modal-header">
        <h2>
          <span class="material-symbols-rounded" style="vertical-align:middle;margin-right:0.5rem;">payments</span>
          Select Payment Method
        </h2>
        <span class="close" onclick="closePOSPaymentMethodModal()">&times;</span>
      </div>
      <div class="modal-body">
        <p style="color:#6b7280;margin-bottom:1.5rem;text-align:center;">Choose the payment method for this order:</p>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;margin-bottom:1.5rem;">
          <button class="payment-method-btn" data-method="Cash" onclick="selectPaymentMethod('Cash')" style="padding:1.5rem;border:2px solid #e5e7eb;border-radius:12px;background:white;cursor:pointer;transition:all 0.2s;text-align:center;">
            <span class="material-symbols-rounded" style="font-size:2.5rem;color:#10b981;display:block;margin-bottom:0.5rem;">money</span>
            <div style="font-weight:600;color:#1f2937;">Cash</div>
          </button>
          <button class="payment-method-btn" data-method="Card" onclick="selectPaymentMethod('Card')" style="padding:1.5rem;border:2px solid #e5e7eb;border-radius:12px;background:white;cursor:pointer;transition:all 0.2s;text-align:center;">
            <span class="material-symbols-rounded" style="font-size:2.5rem;color:#3b82f6;display:block;margin-bottom:0.5rem;">credit_card</span>
            <div style="font-weight:600;color:#1f2937;">Card</div>
          </button>
          <button class="payment-method-btn" data-method="UPI" onclick="selectPaymentMethod('UPI')" style="padding:1.5rem;border:2px solid #e5e7eb;border-radius:12px;background:white;cursor:pointer;transition:all 0.2s;text-align:center;">
            <span class="material-symbols-rounded" style="font-size:2.5rem;color:#8b5cf6;display:block;margin-bottom:0.5rem;">qr_code</span>
            <div style="font-weight:600;color:#1f2937;">UPI</div>
          </button>
          <button class="payment-method-btn" data-method="Online" onclick="selectPaymentMethod('Online')" style="padding:1.5rem;border:2px solid #e5e7eb;border-radius:12px;background:white;cursor:pointer;transition:all 0.2s;text-align:center;">
            <span class="material-symbols-rounded" style="font-size:2.5rem;color:#f59e0b;display:block;margin-bottom:0.5rem;">language</span>
            <div style="font-weight:600;color:#1f2937;">Online</div>
          </button>
        </div>
        <div class="form-actions" style="justify-content:center;">
          <button type="button" class="btn btn-cancel" onclick="closePOSPaymentMethodModal()">
            <span class="material-symbols-rounded">close</span>
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Payment Method Modal (Add/Edit) -->
  <div id="paymentMethodModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 500px;">
      <div class="modal-header">
        <h2 id="paymentMethodModalTitle">Add Payment Method</h2>
        <span class="close" onclick="closePaymentMethodModal()">&times;</span>
      </div>
      <div class="modal-body">
        <form id="paymentMethodForm">
          <input type="hidden" id="paymentMethodId" />
          <div class="form-group">
            <label for="paymentMethodName">Method Name *</label>
            <input type="text" id="paymentMethodName" placeholder="e.g., PayPal, Apple Pay" required />
          </div>
          <div class="form-group">
            <label for="paymentMethodEmoji">Emoji (Optional)</label>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
              <input type="text" id="paymentMethodEmoji" placeholder="💳 or click below" maxlength="10" style="flex: 1;" />
              <div style="display: flex; gap: 0.25rem; flex-wrap: wrap; max-width: 200px;">
                <button type="button" class="emoji-btn" onclick="selectEmoji('💵')" title="Cash">💵</button>
                <button type="button" class="emoji-btn" onclick="selectEmoji('💳')" title="Card">💳</button>
                <button type="button" class="emoji-btn" onclick="selectEmoji('📱')" title="UPI">📱</button>
                <button type="button" class="emoji-btn" onclick="selectEmoji('🌐')" title="Online">🌐</button>
                <button type="button" class="emoji-btn" onclick="selectEmoji('👛')" title="Wallet">👛</button>
                <button type="button" class="emoji-btn" onclick="selectEmoji('🏦')" title="Bank">🏦</button>
                <button type="button" class="emoji-btn" onclick="selectEmoji('📝')" title="Cheque">📝</button>
                <button type="button" class="emoji-btn" onclick="selectEmoji('₿')" title="Crypto">₿</button>
                <button type="button" class="emoji-btn" onclick="selectEmoji('💎')" title="Diamond">💎</button>
                <button type="button" class="emoji-btn" onclick="selectEmoji('🎁')" title="Gift">🎁</button>
                <button type="button" class="emoji-btn" onclick="selectEmoji('💰')" title="Money">💰</button>
                <button type="button" class="emoji-btn" onclick="selectEmoji('💸')" title="Money Wings">💸</button>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>
              <input type="checkbox" id="paymentMethodActive" checked />
              Active (visible in payment options)
            </label>
          </div>
          <div class="form-actions">
            <button type="button" class="btn btn-cancel" onclick="closePaymentMethodModal()">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Method</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Restaurant Logo Upload Modal -->
  <div id="logoUploadModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:500px;">
      <div class="modal-header">
        <h2>
          <span class="material-symbols-rounded" style="vertical-align:middle;margin-right:0.5rem;">image</span>
          Change Restaurant Logo
        </h2>
        <span class="close" onclick="closeLogoUploadModal()">&times;</span>
      </div>
      <div class="modal-body">
        <div style="text-align:center;padding:1rem 0;">
          <div id="logoPreviewContainer" style="margin-bottom:1.5rem;">
            <div id="logoPreview" style="width:150px;height:150px;border-radius:50%;background:#f3f4f6;margin:0 auto;display:flex;align-items:center;justify-content:center;border:3px dashed #d1d5db;overflow:hidden;">
              <span class="material-symbols-rounded" style="font-size:3rem;color:#9ca3af;">image</span>
            </div>
          </div>
          <input type="file" id="logoFileInput" accept="image/*" style="display:none;" onchange="handleLogoFileSelect(event)">
          <button type="button" class="btn btn-primary" onclick="document.getElementById('logoFileInput').click()" style="margin-bottom:1rem;">
            <span class="material-symbols-rounded">upload</span>
            Choose Image
          </button>
          <p style="color:#6b7280;font-size:0.875rem;margin:0.5rem 0;">Recommended: Square image, max 2MB (JPG, PNG, WebP)</p>
        </div>
        <div class="form-actions" style="justify-content:center;margin-top:1.5rem;">
          <button type="button" class="btn btn-cancel" onclick="closeLogoUploadModal()">
            <span class="material-symbols-rounded">close</span>
            Cancel
          </button>
          <button type="button" class="btn btn-save" id="saveLogoBtn" onclick="uploadRestaurantLogo()" disabled>
            <span class="material-symbols-rounded">save</span>
            Save Logo
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Script -->
  <script src="../assets/js/script.js?v=<?php echo time(); ?>" defer></script>
  <script>
    // Check payment status on page load (for redirect from PhonePe or Demo)
    window.addEventListener('load', function() {
      const urlParams = new URLSearchParams(window.location.search);
      const demoSuccess = urlParams.get('demo_payment_success');
      const demoCancelled = urlParams.get('demo_payment_cancelled');
      
      // Handle demo payment success
      if (demoSuccess === 'true') {
        const transactionId = urlParams.get('transaction_id');
        if (transactionId) {
          showNotification('Demo payment successful! Your subscription has been activated.', 'success');
          if (typeof loadRestaurantInfo === 'function') {
            setTimeout(() => loadRestaurantInfo(), 1000);
          }
          // Clear URL parameters
          window.history.replaceState({}, document.title, window.location.pathname);
          return;
        }
      }
      
      // Handle demo payment cancelled
      if (demoCancelled === 'true') {
        showNotification('Payment cancelled.', 'error');
        window.history.replaceState({}, document.title, window.location.pathname);
        return;
      }
      
      // Check if we just returned from real PhonePe payment
      const justReturned = sessionStorage.getItem('payment_processing');
      if (justReturned === 'true') {
        sessionStorage.removeItem('payment_processing');
        
        // Check payment status from server
        fetch('../api/check_payment_status.php')
          .then(response => response.json())
          .then(result => {
            if (result.success) {
              if (result.payment.payment_status === 'success') {
                showNotification('Payment successful! Your subscription has been activated.', 'success');
                // Reload subscription info
                if (typeof loadRestaurantInfo === 'function') {
                  setTimeout(() => loadRestaurantInfo(), 1000);
                }
              } else if (result.payment.payment_status === 'failed') {
                showNotification('Payment failed. Please try again.', 'error');
              } else {
                // Payment still pending, check again after 2 seconds
                setTimeout(() => {
                  fetch('../api/check_payment_status.php')
                    .then(r => r.json())
                    .then(r => {
                      if (r.success && r.payment.payment_status === 'success') {
                        showNotification('Payment successful! Your subscription has been activated.', 'success');
                        if (typeof loadRestaurantInfo === 'function') {
                          loadRestaurantInfo();
                        }
                      }
                    });
                }, 2000);
              }
            }
          })
          .catch(error => {
            console.error('Error checking payment status:', error);
          });
      }
    });
    
    // Force load dashboard on page load
    window.addEventListener('load', function() {
      console.log('Page loaded, checking dashboard...');
      setTimeout(function() {
        const dashboardPage = document.getElementById('dashboardPage');
        if (dashboardPage && dashboardPage.classList.contains('active')) {
          console.log('Dashboard is active, loading stats...');
          if (typeof loadDashboardStats === 'function') {
            loadDashboardStats();
          } else {
            console.error('loadDashboardStats function not found');
          }
        }
      }, 300);
    });
    
    // Ensure mobile scrolling works on all devices
    (function() {
      function enableScrolling() {
        if (window.innerWidth <= 768) {
          // Ensure html allows body to grow
          document.documentElement.style.overflowY = 'auto';
          document.documentElement.style.height = 'auto';
          document.documentElement.style.minHeight = '100%';
          document.documentElement.style.maxHeight = 'none';
          
          // Ensure body can scroll and grow
          document.body.style.overflowY = 'auto';
          document.body.style.height = 'auto';
          document.body.style.minHeight = '100vh';
          document.body.style.maxHeight = 'none';
          document.body.style.webkitOverflowScrolling = 'touch';
          document.body.style.touchAction = 'pan-y';
          document.body.style.position = 'relative';
          
          // Ensure main-content doesn't constrain body
          const mainContent = document.querySelector('.main-content');
          if (mainContent) {
            mainContent.style.height = 'auto';
            mainContent.style.minHeight = 'auto';
            mainContent.style.maxHeight = 'none';
            mainContent.style.overflow = 'visible';
            mainContent.style.position = 'relative';
          }
          
          // Remove height constraints from all page containers
          const containers = document.querySelectorAll('.page, .page-content, .page-header');
          containers.forEach(function(el) {
            el.style.height = 'auto';
            el.style.minHeight = 'auto';
            el.style.maxHeight = 'none';
            el.style.overflow = 'visible';
          });
        }
      }
      
      // Run on load and resize
      enableScrolling();
      window.addEventListener('resize', enableScrolling);
      window.addEventListener('orientationchange', function() {
        setTimeout(enableScrolling, 100);
      });
      
      // Also run after a short delay to ensure DOM is fully loaded
      setTimeout(enableScrolling, 100);
    })();
  </script>
</body>
</html>
