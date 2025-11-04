<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['restaurant_id'])) {
    header('Location: admin/login.php');
    exit();
}
?>
<!DOCTYPE html>
<!-- Coding By CodingNepal - youtube.com/@codingnepal -->
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Restaurant Management System</title>
  <link rel="stylesheet" href="style.css">
  <!-- Linking Google fonts for icons -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">
</head>
<body>
  <aside class="sidebar">
    <!-- Sidebar header -->
    <header class="sidebar-header">
      <a href="#" class="header-logo">
        <img src="logo.png" alt="Restaurant Management">
        <div class="restaurant-info">
          <div class="restaurant-name" id="restaurantName">Restaurant Name</div>
          <div class="restaurant-id" id="restaurantId">RES001</div>
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
                <span class="nav-label">Menu</span>
              </a>
              <span class="nav-tooltip">Menu</span>
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
          <a href="website/index.php" class="nav-link" target="_blank">
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
                <div class="stat-value-large" id="todayRevenue">₹0.00</div>
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
              <a href="website/index.php" class="btn btn-primary" target="_blank">Open Website</a>
            </div>
            <p style="margin-top:10px;color:#666;">Saved locally on this server (no link parameters required). The website reads saved colors automatically from the same origin.</p>
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
    <!-- Menu Management Page -->
    <div id="menuPage" class="page">
      <div class="page-header">
        <h1>Menu Management</h1>
        <p>Create, edit, and manage your menus</p>
      </div>
      <div class="page-content">
        <div class="menu-actions">
          <button class="btn btn-primary" id="addMenuBtn">
            <span class="material-symbols-rounded">add</span>
            Add New Menu
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
        <div class="report-filters" style="display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;">
          <div style="flex: 1; min-width: 200px;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Period</label>
            <select id="reportPeriod" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
              <option value="today">Today</option>
              <option value="week">This Week</option>
              <option value="month">This Month</option>
              <option value="year">This Year</option>
            </select>
          </div>
          <div style="flex: 1; min-width: 200px;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Report Type</label>
            <select id="reportType" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
              <option value="sales">Sales Report</option>
              <option value="orders">Orders</option>
              <option value="items">Top Items</option>
            </select>
          </div>
          <div style="display: flex; align-items: flex-end;">
            <button onclick="loadReports()" style="padding: 0.75rem 2rem; background: var(--primary-red); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
              <span class="material-symbols-rounded">refresh</span> Refresh
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
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-red);" id="reportTotalSales">₹0.00</div>
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
        <div class="settings-grid">
          <!-- Restaurant Information -->
          <div class="settings-section">
            <h2 class="settings-section-title">
              <span class="material-symbols-rounded">restaurant</span>
              Restaurant Information
            </h2>
            <form id="restaurantSettingsForm">
              <div class="form-row">
                <div class="form-group">
                  <label for="restaurantNameSetting">Restaurant Name</label>
                  <input type="text" id="restaurantNameSetting" placeholder="Enter restaurant name">
                </div>
                <div class="form-group">
                  <label for="restaurantIdSetting">Restaurant ID</label>
                  <input type="text" id="restaurantIdSetting" placeholder="Enter restaurant ID" readonly>
                </div>
              </div>
              
              <div class="form-group">
                <label for="restaurantAddress">Address</label>
                <textarea id="restaurantAddress" rows="3" placeholder="Enter restaurant address"></textarea>
              </div>
              
              <div class="form-row">
                <div class="form-group">
                  <label for="restaurantPhone">Phone Number</label>
                  <input type="tel" id="restaurantPhone" placeholder="Enter phone number">
                </div>
                <div class="form-group">
                  <label for="restaurantEmail">Email</label>
                  <input type="email" id="restaurantEmail" placeholder="Enter email">
                </div>
              </div>
              
              <div class="form-actions">
                <button type="button" class="btn btn-cancel">Cancel</button>
                <button type="submit" class="btn btn-save">Save Changes</button>
              </div>
            </form>
          </div>
          
          <!-- System Settings -->
          <div class="settings-section">
            <h2 class="settings-section-title">
              <span class="material-symbols-rounded">tune</span>
              System Settings
            </h2>
            <form id="systemSettingsForm">
              <div class="form-group">
                <label for="currencySymbol">Currency Symbol</label>
                <input type="text" id="currencySymbol" value="₹" maxlength="3">
              </div>
              
              <div class="form-group">
                <label for="timezone">Timezone</label>
                <select id="timezone">
                  <option value="Asia/Kolkata">Asia/Kolkata (IST)</option>
                  <option value="UTC">UTC</option>
                  <option value="America/New_York">America/New_York (EST)</option>
                  <option value="Europe/London">Europe/London (GMT)</option>
                </select>
              </div>
              
              <div class="form-group">
                <label class="checkbox-label">
                  <input type="checkbox" id="autoSync">
                  <span class="checkmark"></span>
                  Enable Auto Sync
                </label>
              </div>
              
              <div class="form-group">
                <label class="checkbox-label">
                  <input type="checkbox" id="notifications">
                  <span class="checkmark"></span>
                  Enable Push Notifications
                </label>
              </div>
              
              <div class="form-actions">
                <button type="button" class="btn btn-cancel">Cancel</button>
                <button type="submit" class="btn btn-save">Save Changes</button>
              </div>
            </form>
          </div>
          
          <!-- Security Settings -->
          <div class="settings-section">
            <h2 class="settings-section-title">
              <span class="material-symbols-rounded">security</span>
              Security Settings
            </h2>
            <form id="securitySettingsForm">
              <div class="form-group">
                <label for="currentPassword">Current Password</label>
                <input type="password" id="currentPassword" placeholder="Enter current password">
              </div>
              
              <div class="form-group">
                <label for="newPassword">New Password</label>
                <input type="password" id="newPassword" placeholder="Enter new password">
              </div>
              
              <div class="form-group">
                <label for="confirmPassword">Confirm Password</label>
                <input type="password" id="confirmPassword" placeholder="Confirm new password">
              </div>
              
              <div class="form-actions">
                <button type="button" class="btn btn-cancel">Cancel</button>
                <button type="submit" class="btn btn-save">Change Password</button>
              </div>
            </form>
          </div>
          
          <!-- Profile Settings -->
          <div class="settings-section">
            <h2 class="settings-section-title">
              <span class="material-symbols-rounded">account_circle</span>
              Profile Settings
            </h2>
            <form id="profileSettingsForm">
              <div class="form-group">
                <label for="usernameSetting">Username</label>
                <input type="text" id="usernameSetting" placeholder="Enter username">
              </div>
              
              <div class="form-group">
                <label for="displayName">Display Name</label>
                <input type="text" id="displayName" placeholder="Enter display name">
              </div>
              
              <div class="form-group">
                <label class="checkbox-label">
                  <input type="checkbox" id="emailNotifications">
                  <span class="checkmark"></span>
                  Email Notifications
                </label>
              </div>
              
              <div class="form-actions">
                <button type="button" class="btn btn-cancel">Cancel</button>
                <button type="submit" class="btn btn-save">Update Profile</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Profile Page -->
    <div id="profilePage" class="page">
      <div class="page-header">
        <h1>Profile</h1>
        <p>Manage your account information</p>
      </div>
      <div class="page-content">
        <div class="profile-container">
          <div class="profile-header">
            <div class="profile-avatar-large">
              <span id="profileInitials">JD</span>
            </div>
            <div class="profile-info">
              <h2 id="profileName">John Doe</h2>
              <p id="profileRole">Administrator</p>
              <p id="profileEmail">john.doe@restaurant.com</p>
            </div>
          </div>
          
          <div class="profile-details">
            <div class="profile-card">
              <h3>Account Information</h3>
              <div class="info-row">
                <span class="info-label">Username:</span>
                <span class="info-value" id="infoUsername">johndoe</span>
              </div>
              <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value" id="infoEmail">john.doe@restaurant.com</span>
              </div>
              <div class="info-row">
                <span class="info-label">Role:</span>
                <span class="info-value" id="infoRole">Administrator</span>
              </div>
              <div class="info-row">
                <span class="info-label">Member Since:</span>
                <span class="info-value" id="infoMemberSince">January 2024</span>
              </div>
            </div>
            
            <div class="profile-card">
              <h3>Statistics</h3>
              <div class="stat-grid">
                <div class="stat-box">
                  <div class="stat-icon">📊</div>
                  <div class="stat-info">
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value">1,234</div>
                  </div>
                </div>
                <div class="stat-box">
                  <div class="stat-icon">✅</div>
                  <div class="stat-info">
                    <div class="stat-label">Completed</div>
                    <div class="stat-value">1,189</div>
                  </div>
                </div>
                <div class="stat-box">
                  <div class="stat-icon">👥</div>
                  <div class="stat-info">
                    <div class="stat-label">Team Size</div>
                    <div class="stat-value">12</div>
                  </div>
                </div>
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
        <div class="menu-actions">
          <button class="btn btn-primary" id="addReservationBtn">
            <span class="material-symbols-rounded">add</span>
            New Reservation
          </button>
        </div>
        
        <div class="menu-list" id="reservationList">
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
                <span id="cartSubtotal">₹0.00</span>
              </div>
              <div class="cart-summary-row">
                <span>CGST (2.5%):</span>
                <span id="cartCGST">₹0.00</span>
              </div>
              <div class="cart-summary-row">
                <span>SGST (2.5%):</span>
                <span id="cartSGST">₹0.00</span>
              </div>
              <div class="cart-summary-row">
                <span>GST (5%):</span>
                <span id="cartTax">₹0.00</span>
              </div>
              <div class="cart-summary-row total">
                <span>Total:</span>
                <span id="cartTotal">₹0.00</span>
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
                <th>Total Visits</th>
                <th>Total Spent</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="customerList">
              <tr>
                <td colspan="7" class="loading">Loading customers...</td>
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
        <h2 id="modalTitle">Add New Menu</h2>
        <span class="close">&times;</span>
      </div>
      <div class="modal-body">
        <form id="menuForm">
          <input type="hidden" id="menuId" name="menuId" value="">
          <div class="form-group">
            <label for="menuName">Menu Name:</label>
            <input type="text" id="menuName" name="menuName" required placeholder="Enter menu name (e.g., Breakfast, Lunch, Dinner)">
          </div>
          <div class="form-actions">
            <button type="button" class="btn btn-cancel" id="cancelBtn">Cancel</button>
            <button type="submit" class="btn btn-save" id="saveBtn">Save Menu</button>
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
              <label for="itemCategory">Item Category:</label>
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
            <div id="imagePreview" class="image-preview" style="display: none;">
              <img id="previewImg" src="" alt="Preview">
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="basePrice">Price:</label>
              <div class="price-input">
                <span class="currency-symbol">₹</span>
                <input type="number" id="basePrice" name="basePrice" min="0" step="0.01" value="0.00" placeholder="0.00">
              </div>
            </div>
            
            <div class="form-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" id="hasVariations" name="hasVariations">
                <span class="checkmark"></span>
                Has Variations
              </label>
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
        <form id="reservationForm">
          <input type="hidden" id="reservationId" name="reservationId" value="">
          <div class="form-row">
            <div class="form-group">
              <label for="reservationDate">Date:</label>
              <input type="date" id="reservationDate" name="reservationDate" required>
            </div>
            <div class="form-group">
              <label for="noOfGuests">Guests:</label>
              <input type="number" id="noOfGuests" name="noOfGuests" min="1" value="1" required placeholder="Number of guests">
            </div>
          </div>
          <div class="form-group">
            <label for="mealType">Meal Type:</label>
            <select id="mealType" name="mealType" required>
              <option value="Breakfast">Breakfast</option>
              <option value="Lunch" selected>Lunch</option>
              <option value="Dinner">Dinner</option>
              <option value="Snacks">Snacks</option>
            </select>
          </div>
          <div class="form-group">
            <label for="timeSlot">Select Time Slot:</label>
            <div id="timeSlots" class="time-slots">
              <!-- Time slots will be added dynamically -->
            </div>
          </div>
          <div class="form-group">
            <label for="specialRequest">Any special request?</label>
            <textarea id="specialRequest" name="specialRequest" rows="3" placeholder="Enter any special requests..."></textarea>
          </div>
          <div class="form-group">
            <label for="customerName">Customer Name:</label>
            <input type="text" id="customerName" name="customerName" required placeholder="Enter customer name">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="phone">Phone:</label>
              <input type="tel" id="phone" name="phone" required placeholder="Enter phone number">
            </div>
            <div class="form-group">
              <label for="email">Email Address:</label>
              <input type="email" id="email" name="email" required placeholder="Enter email address">
            </div>
          </div>
          <div class="form-group">
            <label for="selectTable">Assign Table:</label>
            <select id="selectTable" name="selectTable">
              <option value="">-- Select Table --</option>
            </select>
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
            <input type="text" id="customerNameInput" name="customerName" required placeholder="Enter customer name">
          </div>
          <div class="form-group">
            <label for="customerPhoneInput">Phone:</label>
            <input type="tel" id="customerPhoneInput" name="phone" required placeholder="Enter phone number">
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
      <p style="color:#6b7280;margin:0 0 1.5rem;line-height:1.6;">Your trial has expired or account is disabled. Please renew your subscription to continue using the service.</p>
      <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
        <button id="renewButton" onclick="initiateRenewal()" style="padding:12px 24px;background:#10b981;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;transition:opacity 0.2s;display:flex;align-items:center;gap:8px;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
          <span style="font-size:1.2rem;">💳</span>
          Renew Now (₹999)
        </button>
        <button onclick="document.getElementById('renewalModal').style.display='none';" style="padding:12px 24px;background:#6b7280;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;transition:opacity 0.2s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Close</button>
      </div>
      <p style="color:#9ca3af;font-size:0.875rem;margin-top:1rem;margin-bottom:0;">1 month subscription - Auto-renewable</p>
    </div>
  </div>

  <!-- Script -->
  <script src="script.js?v=<?php echo time(); ?>"></script>
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
        fetch('check_payment_status.php')
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
                  fetch('check_payment_status.php')
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
  </script>
</body>
</html>
