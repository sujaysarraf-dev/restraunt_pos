<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Menu - Order Online</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
</head>
<body>
    <script>
      // Fetch theme from DB via API and apply CSS variables and banner
      document.addEventListener('DOMContentLoaded', async function(){
        try {
          const params = new URLSearchParams(location.search);
          const rid = params.get('restaurant_id');
          const q = rid ? `?action=get&restaurant_id=${encodeURIComponent(rid)}` : '?action=get';
          const res = await fetch('theme_api.php'+q);
          const data = await res.json();
          if (data.success && data.settings) {
            const t = data.settings, r = document.documentElement;
            r.style.setProperty('--primary-red', t.primary_red || '#F70000');
            r.style.setProperty('--dark-red', t.dark_red || '#DA020E');
            r.style.setProperty('--primary-yellow', t.primary_yellow || '#FFD100');
            
            // Set banner slideshow if exists
            const bannerSection = document.querySelector('.banner-section');
            const bannerSlideshow = document.getElementById('bannerSlideshow');
            if (bannerSection && bannerSlideshow) {
              const banners = t.banners || [];
              
              if (banners.length > 0) {
                // Clear existing slides
                bannerSlideshow.innerHTML = '';
                
                // Create slides
                banners.forEach((banner, index) => {
                  const slide = document.createElement('div');
                  slide.className = 'banner-slide' + (index === 0 ? ' active' : '');
                  slide.innerHTML = `<img src="../${banner.banner_path}" alt="Banner ${index + 1}" style="width:100%;height:auto;display:block;object-fit:cover;max-height:400px;">`;
                  bannerSlideshow.appendChild(slide);
                });
                
                // Initialize slideshow with smooth fade transitions
                let currentSlide = 0;
                const slides = bannerSlideshow.querySelectorAll('.banner-slide');
                
                const slideInterval = setInterval(() => {
                  if (banners.length > 1) {
                    // Fade out current slide
                    slides[currentSlide].classList.remove('active');
                    currentSlide = (currentSlide + 1) % banners.length;
                    // Fade in next slide
                    setTimeout(() => {
                      slides[currentSlide].classList.add('active');
                    }, 100);
                  }
                }, 3000); // 3 seconds per slide
                
                // Store interval ID for cleanup if needed
                bannerSlideshow.dataset.intervalId = slideInterval;
                
                bannerSection.style.display = 'block';
                bannerSection.style.visibility = 'visible';
                console.log('Banner slideshow loaded:', banners.length, 'banners');
              } else if (t.banner_image && t.banner_image !== 'null' && t.banner_image.trim() !== '') {
                // Fallback to single banner (backward compatibility)
                const slide = document.createElement('div');
                slide.className = 'banner-slide active';
                slide.innerHTML = `<img src="../${t.banner_image}" alt="Banner" style="width:100%;height:auto;display:block;object-fit:cover;max-height:400px;">`;
                bannerSlideshow.innerHTML = '';
                bannerSlideshow.appendChild(slide);
                bannerSection.style.display = 'block';
                bannerSection.style.visibility = 'visible';
                console.log('Single banner loaded (backward compatibility)');
              } else {
                bannerSection.style.display = 'none';
                console.log('No banner images in database');
              }
            } else {
              console.error('Banner section or slideshow element not found in DOM');
            }
          }
        } catch(e) { 
          console.error('Error loading theme/banner:', e);
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
    <section class="banner-section" id="banner">
        <div id="bannerSlideshow" class="banner-slideshow"></div>
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
                <span>Total: ₹<span id="cartTotal">0.00</span></span>
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

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Restaurant. All rights reserved.</p>
        </div>
    </footer>

    <script src="script.js"></script>
</body>
</html>

