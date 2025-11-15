<?php
// Load currency symbol from database server-side (same as dashboard)
// Get restaurant_id from URL or default to RES001
$restaurant_id = isset($_GET['restaurant_id']) && $_GET['restaurant_id'] !== '' 
    ? $_GET['restaurant_id'] 
    : 'RES001';

// Default currency symbol
$currency_symbol = '₹';

try {
    // Include database connection
    require_once __DIR__ . '/db_config.php';
    
    // Get connection
    if (isset($pdo) && $pdo instanceof PDO) {
        $conn = $pdo;
    } else {
        // Fallback connection if needed
        $host = 'localhost';
        $dbname = 'restro2';
        $username = 'root';
        $password = '';
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    
    // Get currency symbol from users table based on restaurant_id
    try {
        $stmt = $conn->prepare("SELECT currency_symbol FROM users WHERE restaurant_id = ? LIMIT 1");
        $stmt->execute([$restaurant_id]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($userRow) {
            // Currency symbol - load server-side to prevent flash
            if (array_key_exists('currency_symbol', $userRow) && $userRow['currency_symbol'] !== null && $userRow['currency_symbol'] !== '') {
                $db_currency = trim($userRow['currency_symbol']);
                if ($db_currency !== '') {
                    $currency_symbol = htmlspecialchars($db_currency, ENT_QUOTES, 'UTF-8');
                }
            }
        }
    } catch (PDOException $e) {
        // Use default currency if query fails
        error_log("Error loading currency symbol: " . $e->getMessage());
    }
    
    // Load theme colors and banners from database server-side (prevent flash)
    $primary_red = '#F70000';
    $dark_red = '#DA020E';
    $primary_yellow = '#FFD100';
    $banners = [];
    $banner_image = null;
    
    try {
        // Get theme colors from website_settings table
        $stmt = $conn->prepare("SELECT primary_red, dark_red, primary_yellow, banner_image FROM website_settings WHERE restaurant_id = ? LIMIT 1");
        $stmt->execute([$restaurant_id]);
        $themeRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($themeRow) {
            if (!empty($themeRow['primary_red'])) {
                $primary_red = htmlspecialchars($themeRow['primary_red'], ENT_QUOTES, 'UTF-8');
            }
            if (!empty($themeRow['dark_red'])) {
                $dark_red = htmlspecialchars($themeRow['dark_red'], ENT_QUOTES, 'UTF-8');
            }
            if (!empty($themeRow['primary_yellow'])) {
                $primary_yellow = htmlspecialchars($themeRow['primary_yellow'], ENT_QUOTES, 'UTF-8');
            }
            if (!empty($themeRow['banner_image']) && trim($themeRow['banner_image']) !== '') {
                $banner_image = htmlspecialchars($themeRow['banner_image'], ENT_QUOTES, 'UTF-8');
            }
        }
        
        // Get banners from website_banners table
        try {
            $bannersStmt = $conn->prepare("SELECT id, banner_path, display_order FROM website_banners WHERE restaurant_id = ? ORDER BY display_order ASC, id ASC");
            $bannersStmt->execute([$restaurant_id]);
            $banners = $bannersStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Table might not exist, use empty array
            error_log("Error loading banners: " . $e->getMessage());
        }
    } catch (PDOException $e) {
        // Use default colors if query fails
        error_log("Error loading theme settings: " . $e->getMessage());
    }
} catch (Exception $e) {
    // Use default currency if database connection fails
    error_log("Database connection error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Restaurant Menu - Order Online</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
      /* Theme colors loaded server-side from database - prevents flash */
      :root {
        --primary-red: <?php echo htmlspecialchars($primary_red, ENT_QUOTES, 'UTF-8'); ?>;
        --dark-red: <?php echo htmlspecialchars($dark_red, ENT_QUOTES, 'UTF-8'); ?>;
        --primary-yellow: <?php echo htmlspecialchars($primary_yellow, ENT_QUOTES, 'UTF-8'); ?>;
      }
    </style>
    <script>
      // Set currency symbol from server-side (like restaurant logo/name)
      // This prevents any flash of default currency symbol
      window.globalCurrencySymbol = <?php echo json_encode($currency_symbol, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
      localStorage.setItem('system_currency', window.globalCurrencySymbol);
    </script>
</head>
<body>
    <script>
      // Theme colors and banners are now loaded server-side (no flash)
      // Only initialize banner slideshow rotation if multiple banners exist
      document.addEventListener('DOMContentLoaded', function(){
        const bannerSlideshow = document.getElementById('bannerSlideshow');
        if (bannerSlideshow) {
          const slides = bannerSlideshow.querySelectorAll('.banner-slide');
          if (slides.length > 1) {
            // Initialize slideshow rotation for multiple banners
            let currentSlide = 0;
            const slideInterval = setInterval(() => {
              // Fade out current slide
              slides[currentSlide].classList.remove('active');
              currentSlide = (currentSlide + 1) % slides.length;
              // Fade in next slide
              setTimeout(() => {
                slides[currentSlide].classList.add('active');
              }, 100);
            }, 3000); // 3 seconds per slide
            
            // Store interval ID for cleanup if needed
            bannerSlideshow.dataset.intervalId = slideInterval;
          }
        }
      });
    </script>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h1>🍔 Restaurant</h1>
            </div>
            <div class="nav-menu">
                <a href="#home" class="nav-link active">Home</a>
                <a href="#menu" class="nav-link">Menu</a>
                <a href="#about" class="nav-link">About</a>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <div class="reservation-btn" id="reservationBtn">
                    <span class="material-symbols-rounded">event</span>
                    <span>Reservation</span>
                </div>
                <div class="call-waiter-btn" id="callWaiterBtn">
                    <span class="material-symbols-rounded">room_service</span>
                    <span>Call Waiter</span>
                </div>
                <div class="nav-cart" id="cartIcon">
                    <span class="material-symbols-rounded">shopping_cart</span>
                    <span class="cart-count" id="cartCount">0</span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <h1 class="hero-title">Delicious Food<br>Delivered Fresh</h1>
            <p class="hero-subtitle">Order your favorite meals online and enjoy fast delivery</p>
            <div class="hero-search">
                <input type="text" id="searchInput" placeholder="Search for food...">
                <button class="search-btn">
                    <span class="material-symbols-rounded">search</span>
                </button>
            </div>
        </div>
    </section>

    <!-- Banner Section -->
    <section class="banner-section" id="banner" style="<?php echo (empty($banners) && empty($banner_image)) ? 'display: none;' : 'display: block; visibility: visible;'; ?>">
        <div id="bannerSlideshow" class="banner-slideshow">
            <?php
            // Output banners server-side to prevent flash
            if (!empty($banners) && is_array($banners)) {
                foreach ($banners as $index => $banner) {
                    $bannerPath = htmlspecialchars($banner['banner_path'], ENT_QUOTES, 'UTF-8');
                    $activeClass = $index === 0 ? ' active' : '';
                    echo '<div class="banner-slide' . $activeClass . '">';
                    echo '<img src="../' . $bannerPath . '" alt="Banner ' . ($index + 1) . '" style="width:100%;height:auto;display:block;object-fit:cover;max-height:400px;">';
                    echo '</div>';
                }
            } elseif (!empty($banner_image)) {
                // Fallback to single banner (backward compatibility)
                $bannerPath = htmlspecialchars($banner_image, ENT_QUOTES, 'UTF-8');
                echo '<div class="banner-slide active">';
                echo '<img src="../' . $bannerPath . '" alt="Banner" style="width:100%;height:auto;display:block;object-fit:cover;max-height:400px;">';
                echo '</div>';
            }
            ?>
        </div>
    </section>

    <!-- Category Filter -->
    <section class="categories" id="menu">
        <div class="container">
            <h2 class="section-title">Browse Categories</h2>
            <div class="category-tabs" id="menuTabs">
                <button class="category-btn active" data-menu="all">All Items</button>
            </div>
        </div>
    </section>

    <!-- Filter Bar -->
    <section class="filters">
        <div class="container">
            <div class="filter-group">
                <label>Type:</label>
                <select id="typeFilter">
                    <option value="">All Types</option>
                    <option value="Veg">Veg</option>
                    <option value="Non Veg">Non Veg</option>
                    <option value="Egg">Egg</option>
                    <option value="Drink">Drink</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Category:</label>
                <select id="categoryFilter">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div class="filter-group">
                <button class="clear-filters" onclick="clearFilters()">Clear Filters</button>
            </div>
        </div>
    </section>

    <!-- Menu Items -->
    <section class="menu-items">
        <div class="container">
            <div class="menu-grid" id="menuGrid">
                <div class="loading">Loading menu items...</div>
            </div>
        </div>
    </section>

    <!-- Cart Sidebar -->
    <div class="cart-overlay" id="cartOverlay"></div>
    <div class="cart-sidebar" id="cartSidebar">
        <div class="cart-header">
            <h2>Your Order</h2>
            <button class="close-cart" id="closeCart">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <div class="cart-items" id="cartItems">
            <div class="empty-cart">
                <span class="material-symbols-rounded">shopping_cart</span>
                <p>Your cart is empty</p>
            </div>
        </div>
        <div class="cart-footer">
            <div class="cart-total">
                <span>Total: <?php echo htmlspecialchars($currency_symbol); ?><span id="cartTotal">0.00</span></span>
            </div>
            <!-- Show Call Waiter button if table is in URL and cart has items -->
            <div id="continueSection" style="display: none;">
                <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: center; padding: 0.75rem; background: var(--primary-red); color: white; border-radius: 12px; font-weight: 600; cursor: pointer; margin-bottom: 1rem;" onclick="completeSelection()">
                    <span class="material-symbols-rounded">arrow_forward</span>
                    <span>Continue</span>
                </div>
                <div class="call-waiter-action" id="callWaiterAction" style="display: none;">
                    <button class="checkout-btn" style="background: #28a745; width: 100%;" onclick="triggerCallWaiter()">
                        <span class="material-symbols-rounded">room_service</span>
                        Call Waiter
                    </button>
                </div>
            </div>
            <!-- Regular checkout for website orders -->
            <button class="checkout-btn" id="checkoutBtn" disabled>Checkout</button>
        </div>
    </div>

    <!-- Call Waiter Modal -->
    <div class="waiter-modal" id="waiterModal">
        <div class="waiter-modal-content">
            <div class="waiter-modal-header">
                <h2>Call Waiter</h2>
                <button class="close-modal" id="closeWaiterModal">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <div class="waiter-modal-body">
                <p style="margin-bottom: 1.5rem;">Please select your table:</p>
                <div class="table-grid" id="tableGrid">
                    <div class="loading">Loading tables...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reservation Modal -->
    <div class="waiter-modal" id="reservationModal" onclick="closeReservationModalOnOverlay(event)">
        <div class="waiter-modal-content reservation-modal-content" onclick="event.stopPropagation();">
            <div class="waiter-modal-header">
                <h2>Make a Reservation</h2>
                <button class="close-modal" id="closeReservationModal">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <div class="waiter-modal-body">
                <div id="reservationTableSelection" style="display: block;">
                    <p style="margin-bottom: 1.5rem;">Please select your table:</p>
                    <div class="table-grid" id="reservationTableGrid">
                        <div class="loading">Loading tables...</div>
                    </div>
                </div>
                <div id="reservationFormSection" style="display: none;">
                    <form id="reservationForm">
                        <input type="hidden" id="selectedTableId" name="tableId">
                        <div id="tableCapacityInfo" style="display: none; padding: 0.75rem 1rem; background: #e7f5ff; border-radius: 8px; margin-bottom: 1.5rem; color: #0066cc; font-weight: 500; font-size: 0.95rem; text-align: center;">
                            <!-- Table capacity info will be shown here -->
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Date:</label>
                            <input type="date" id="reservationDate" name="reservationDate" required style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Time Slot:</label>
                            <select id="reservationTimeSlot" name="timeSlot" required style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                                <option value="">Select Time</option>
                                <option value="09:00">09:00 AM</option>
                                <option value="10:00">10:00 AM</option>
                                <option value="11:00">11:00 AM</option>
                                <option value="12:00">12:00 PM</option>
                                <option value="13:00">01:00 PM</option>
                                <option value="14:00">02:00 PM</option>
                                <option value="15:00">03:00 PM</option>
                                <option value="16:00">04:00 PM</option>
                                <option value="17:00">05:00 PM</option>
                                <option value="18:00">06:00 PM</option>
                                <option value="19:00">07:00 PM</option>
                                <option value="20:00">08:00 PM</option>
                                <option value="21:00">09:00 PM</option>
                                <option value="22:00">10:00 PM</option>
                            </select>
                            <div id="availabilityWarning" style="display: none; margin-top: 0.5rem; padding: 0.75rem; background: #f8d7da; border: 1px solid #dc3545; border-radius: 6px; color: #721c24; font-size: 0.9rem;">
                                <!-- Availability warning will be shown here -->
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Number of Guests:</label>
                            <input type="number" id="reservationGuests" name="noOfGuests" min="1" value="1" required style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                            <div id="capacityWarning" style="display: none; margin-top: 0.5rem; padding: 0.75rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; color: #856404; font-size: 0.9rem;">
                                <!-- Capacity warning will be shown here -->
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Meal Type:</label>
                            <select id="reservationMealType" name="mealType" required style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                                <option value="Breakfast">Breakfast</option>
                                <option value="Lunch" selected>Lunch</option>
                                <option value="Dinner">Dinner</option>
                                <option value="Snacks">Snacks</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Your Name:</label>
                            <input type="text" id="reservationName" name="customerName" required placeholder="Enter your name" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Phone Number:</label>
                            <input type="tel" id="reservationPhone" name="phone" required placeholder="Enter your phone number" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Email Address:</label>
                            <input type="email" id="reservationEmail" name="email" placeholder="Enter your email (optional)" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Special Requests:</label>
                            <textarea id="reservationSpecialRequest" name="specialRequest" rows="3" placeholder="Any special requests or notes..." style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem; resize: vertical; box-sizing: border-box;"></textarea>
                        </div>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <button type="button" class="btn-no" onclick="backToTableSelection()" style="flex: 1; min-width: 120px; padding: 1rem; font-size: 1rem;">Back</button>
                            <button type="submit" class="btn-yes" style="flex: 1; min-width: 120px; padding: 1rem; font-size: 1.1rem;">Confirm Reservation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="waiter-modal" id="profileModal" onclick="closeProfileModalOnOverlay(event)">
        <div class="waiter-modal-content profile-modal-content" onclick="event.stopPropagation();">
            <div class="waiter-modal-header">
                <h2>My Profile</h2>
                <button class="close-modal" id="closeProfileModal">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <div class="waiter-modal-body">
                <div id="profileContent">
                    <!-- Profile Info Section -->
                    <div class="profile-section">
                        <h3 style="margin-bottom: 1rem; color: var(--primary-red); display: flex; align-items: center; gap: 0.5rem;">
                            <span class="material-symbols-rounded">person</span>
                            Personal Information
                        </h3>
                        <div id="profileInfo" class="profile-info-display">
                            <div class="profile-info-item">
                                <span class="profile-label">Name:</span>
                                <span class="profile-value" id="profileName">-</span>
                            </div>
                            <div class="profile-info-item">
                                <span class="profile-label">Phone:</span>
                                <span class="profile-value" id="profilePhone">-</span>
                            </div>
                            <div class="profile-info-item">
                                <span class="profile-label">Email:</span>
                                <span class="profile-value" id="profileEmail">-</span>
                            </div>
                            <div class="profile-info-item">
                                <span class="profile-label">Address:</span>
                                <span class="profile-value" id="profileAddress">-</span>
                            </div>
                            <button class="btn-edit-profile" onclick="toggleProfileEdit()" style="margin-top: 1rem; width: 100%;">
                                <span class="material-symbols-rounded">edit</span>
                                Edit Profile
                            </button>
                        </div>
                        <div id="profileEdit" class="profile-edit-form" style="display: none;">
                            <form id="profileForm">
                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Full Name:</label>
                                    <input type="text" id="editName" required placeholder="Enter your name" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                                </div>
                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Phone Number:</label>
                                    <input type="tel" id="editPhone" required placeholder="Enter your phone number" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                                </div>
                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Email Address:</label>
                                    <input type="email" id="editEmail" placeholder="Enter your email (optional)" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                                </div>
                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Address:</label>
                                    <textarea id="editAddress" rows="3" placeholder="Enter your address (optional)" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem; resize: vertical; box-sizing: border-box;"></textarea>
                                </div>
                                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                    <button type="button" class="btn-no" onclick="cancelProfileEdit()" style="flex: 1; min-width: 120px; padding: 1rem; font-size: 1rem;">Cancel</button>
                                    <button type="submit" class="btn-yes" style="flex: 1; min-width: 120px; padding: 1rem; font-size: 1.1rem;">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Order History Section -->
                    <div class="profile-section" style="margin-top: 2rem;">
                        <h3 style="margin-bottom: 1rem; color: var(--primary-red); display: flex; align-items: center; gap: 0.5rem;">
                            <span class="material-symbols-rounded">history</span>
                            Order History
                        </h3>
                        <div id="orderHistory" class="order-history-list">
                            <div class="loading" style="text-align: center; padding: 2rem;">Loading order history...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Restaurant. All rights reserved.</p>
        </div>
    </footer>

    <!-- Cart Summary Bar (Yellow Bar Above Bottom Nav) -->
    <div class="cart-summary-bar" id="cartSummaryBar" style="display: none;" onclick="toggleCart()">
        <div class="cart-summary-content">
            <span class="material-symbols-rounded cart-summary-icon" style="font-variation-settings: 'FILL' 0;">shopping_cart</span>
            <div class="cart-summary-info">
                <span id="cartSummaryItems">0 Items</span>
                <span class="cart-summary-separator">|</span>
                <span id="cartSummaryTotal"><?php echo htmlspecialchars($currency_symbol); ?>0</span>
            </div>
            <button class="cart-summary-btn">View Cart</button>
        </div>
    </div>

    <!-- Bottom Navigation Bar (Mobile) -->
    <nav class="bottom-nav">
        <div class="bottom-nav-item active" data-nav="home" onclick="scrollToSection('home', this)">
            <span class="material-symbols-rounded">home</span>
            <span class="bottom-nav-label">Home</span>
        </div>
        <div class="bottom-nav-item" data-nav="menu" onclick="scrollToSection('menu', this)">
            <span class="material-symbols-rounded">restaurant_menu</span>
            <span class="bottom-nav-label">Menu</span>
        </div>
        <div class="bottom-nav-item" data-nav="search" onclick="focusSearch(this)">
            <span class="material-symbols-rounded">search</span>
            <span class="bottom-nav-label">Search</span>
        </div>
        <div class="bottom-nav-item" data-nav="profile" onclick="openProfile(this, event)">
            <span class="material-symbols-rounded">person</span>
            <span class="bottom-nav-label">Profile</span>
        </div>
    </nav>

    <script src="script.js"></script>
</body>
</html>

