<?php
/**
 * Sujay Testing Page
 * Quick testing tools for adding menu, area, table, menu item with demo data
 */

require_once __DIR__ . '/../db_connection.php';

// Get first restaurant ID for testing (or use sujay's restaurant if exists)
try {
    $restaurantStmt = $pdo->query("SELECT id, restaurant_name FROM users WHERE username = 'sujay' LIMIT 1");
    $restaurant = $restaurantStmt->fetch(PDO::FETCH_ASSOC);
    $restaurant_id = $restaurant ? $restaurant['id'] : 1; // Default to ID 1 if sujay doesn't exist
    $restaurant_name = $restaurant ? $restaurant['restaurant_name'] : 'Test Restaurant';
} catch (Exception $e) {
    $restaurant_id = 1;
    $restaurant_name = 'Test Restaurant';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testing Tools - RestroGrow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e40af;
            --primary-dark: #1e3a8a;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
        }

        .navbar {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 1rem 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: var(--gray-600);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--gray-600);
        }

        .demo-section {
            background: white;
            border: 2px dashed var(--primary);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .demo-section h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .demo-section p {
            color: var(--gray-600);
            margin-bottom: 1.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .forms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .form-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 1.5rem;
        }

        .form-card h3 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: var(--gray-900);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .alert.show {
            display: block;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 1rem;
            color: var(--gray-600);
        }

        .loading.show {
            display: block;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">← Back to Testing Dashboard</a>
            <div class="nav-links">
                <a href="index.php">Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Testing Tools</h1>
            <p>Quick tools for adding menu, area, table, and menu items. Restaurant: <strong><?php echo htmlspecialchars($restaurant_name); ?></strong> (ID: <?php echo $restaurant_id; ?>)</p>
        </div>

        <div class="demo-section">
            <h2>🚀 One-Click Demo Data</h2>
            <p>Create a complete demo setup with random values in one click!</p>
            <button class="btn btn-primary" id="demoBtn" onclick="createDemoData()">Create Demo Data</button>
            <div class="loading" id="demoLoading">Creating demo data...</div>
            <div class="alert" id="demoAlert"></div>
        </div>

        <div class="forms-grid">
            <div class="form-card">
                <h3>Add Menu</h3>
                <div class="alert" id="menuAlert"></div>
                <form id="menuForm" onsubmit="addMenu(event)">
                    <div class="form-group">
                        <label for="menuName">Menu Name</label>
                        <input type="text" id="menuName" name="menuName" required placeholder="e.g., Breakfast Menu">
                    </div>
                    <button type="submit" class="btn btn-success">Add Menu</button>
                </form>
            </div>

            <div class="form-card">
                <h3>Add Area</h3>
                <div class="alert" id="areaAlert"></div>
                <form id="areaForm" onsubmit="addArea(event)">
                    <div class="form-group">
                        <label for="areaName">Area Name</label>
                        <input type="text" id="areaName" name="areaName" required placeholder="e.g., Main Hall">
                    </div>
                    <button type="submit" class="btn btn-success">Add Area</button>
                </form>
            </div>

            <div class="form-card">
                <h3>Add Table</h3>
                <div class="alert" id="tableAlert"></div>
                <form id="tableForm" onsubmit="addTable(event)">
                    <div class="form-group">
                        <label for="chooseArea">Select Area</label>
                        <select id="chooseArea" name="chooseArea" required>
                            <option value="">Loading areas...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tableNumber">Table Number</label>
                        <input type="text" id="tableNumber" name="tableNumber" required placeholder="e.g., T1">
                    </div>
                    <div class="form-group">
                        <label for="capacity">Capacity</label>
                        <input type="number" id="capacity" name="capacity" required min="1" value="4">
                    </div>
                    <button type="submit" class="btn btn-success">Add Table</button>
                </form>
            </div>

            <div class="form-card">
                <h3>Add Menu Item</h3>
                <div class="alert" id="itemAlert"></div>
                <form id="itemForm" onsubmit="addMenuItem(event)">
                    <div class="form-group">
                        <label for="chooseMenu">Select Menu</label>
                        <select id="chooseMenu" name="chooseMenu" required>
                            <option value="">Loading menus...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="itemNameEn">Item Name</label>
                        <input type="text" id="itemNameEn" name="itemNameEn" required placeholder="e.g., Margherita Pizza">
                    </div>
                    <div class="form-group">
                        <label for="itemDescriptionEn">Description</label>
                        <input type="text" id="itemDescriptionEn" name="itemDescriptionEn" placeholder="Delicious pizza">
                    </div>
                    <div class="form-group">
                        <label for="itemCategory">Category</label>
                        <input type="text" id="itemCategory" name="itemCategory" placeholder="e.g., Pizza">
                    </div>
                    <div class="form-group">
                        <label for="itemType">Type</label>
                        <select id="itemType" name="itemType">
                            <option value="Veg">Veg</option>
                            <option value="Non-Veg">Non-Veg</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="basePrice">Price</label>
                        <input type="number" id="basePrice" name="basePrice" step="0.01" min="0" value="0.00" required>
                    </div>
                    <button type="submit" class="btn btn-success">Add Menu Item</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const restaurantId = <?php echo $restaurant_id; ?>;

        // Load areas and menus on page load
        window.addEventListener('DOMContentLoaded', () => {
            loadAreas();
            loadMenus();
        });

        async function loadAreas() {
            try {
                const res = await fetch(`https://restrogrow.com/api/get_areas.php?restaurant_id=${restaurantId}`);
                const data = await res.json();
                const select = document.getElementById('chooseArea');
                if (data.success && data.areas) {
                    select.innerHTML = '<option value="">Select Area</option>' + 
                        data.areas.map(a => `<option value="${a.id}">${a.area_name}</option>`).join('');
                } else {
                    select.innerHTML = '<option value="">No areas found</option>';
                }
            } catch (e) {
                console.error('Error loading areas:', e);
            }
        }

        async function loadMenus() {
            try {
                const res = await fetch(`https://restrogrow.com/api/get_menus.php?restaurant_id=${restaurantId}`);
                const data = await res.json();
                const select = document.getElementById('chooseMenu');
                if (data.success && data.menus) {
                    select.innerHTML = '<option value="">Select Menu</option>' + 
                        data.menus.map(m => `<option value="${m.id}">${m.menu_name}</option>`).join('');
                } else {
                    select.innerHTML = '<option value="">No menus found</option>';
                }
            } catch (e) {
                console.error('Error loading menus:', e);
            }
        }

        async function addMenu(e) {
            e.preventDefault();
            const form = e.target;
            const alert = document.getElementById('menuAlert');
            const btn = form.querySelector('button[type="submit"]');
            
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('menuName', document.getElementById('menuName').value);
            formData.append('restaurant_id', restaurantId);

            btn.disabled = true;
            alert.className = 'alert';
            alert.style.display = 'none';

            try {
                const res = await fetch('https://restrogrow.com/sujay/api.php?action=addMenu', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    alert.className = 'alert alert-success show';
                    alert.textContent = data.message || 'Menu added successfully!';
                    form.reset();
                    loadMenus();
                } else {
                    alert.className = 'alert alert-error show';
                    alert.textContent = data.message || 'Failed to add menu';
                }
            } catch (e) {
                alert.className = 'alert alert-error show';
                alert.textContent = 'Error: ' + e.message;
            } finally {
                btn.disabled = false;
            }
        }

        async function addArea(e) {
            e.preventDefault();
            const form = e.target;
            const alert = document.getElementById('areaAlert');
            const btn = form.querySelector('button[type="submit"]');
            
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('areaName', document.getElementById('areaName').value);
            formData.append('restaurant_id', restaurantId);

            btn.disabled = true;
            alert.className = 'alert';
            alert.style.display = 'none';

            try {
                const res = await fetch('https://restrogrow.com/sujay/api.php?action=addArea', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    alert.className = 'alert alert-success show';
                    alert.textContent = data.message || 'Area added successfully!';
                    form.reset();
                    loadAreas();
                } else {
                    alert.className = 'alert alert-error show';
                    alert.textContent = data.message || 'Failed to add area';
                }
            } catch (e) {
                alert.className = 'alert alert-error show';
                alert.textContent = 'Error: ' + e.message;
            } finally {
                btn.disabled = false;
            }
        }

        async function addTable(e) {
            e.preventDefault();
            const form = e.target;
            const alert = document.getElementById('tableAlert');
            const btn = form.querySelector('button[type="submit"]');
            
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('tableNumber', document.getElementById('tableNumber').value);
            formData.append('capacity', document.getElementById('capacity').value);
            formData.append('chooseArea', document.getElementById('chooseArea').value);
            formData.append('restaurant_id', restaurantId);

            btn.disabled = true;
            alert.className = 'alert';
            alert.style.display = 'none';

            try {
                const res = await fetch('https://restrogrow.com/sujay/api.php?action=addTable', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    alert.className = 'alert alert-success show';
                    alert.textContent = data.message || 'Table added successfully!';
                    form.reset();
                    loadAreas();
                } else {
                    alert.className = 'alert alert-error show';
                    alert.textContent = data.message || 'Failed to add table';
                }
            } catch (e) {
                alert.className = 'alert alert-error show';
                alert.textContent = 'Error: ' + e.message;
            } finally {
                btn.disabled = false;
            }
        }

        async function addMenuItem(e) {
            e.preventDefault();
            const form = e.target;
            const alert = document.getElementById('itemAlert');
            const btn = form.querySelector('button[type="submit"]');
            
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('chooseMenu', document.getElementById('chooseMenu').value);
            formData.append('itemNameEn', document.getElementById('itemNameEn').value);
            formData.append('itemDescriptionEn', document.getElementById('itemDescriptionEn').value);
            formData.append('itemCategory', document.getElementById('itemCategory').value);
            formData.append('itemType', document.getElementById('itemType').value);
            formData.append('basePrice', document.getElementById('basePrice').value);
            formData.append('preparationTime', 15);
            formData.append('isAvailable', 1);
            formData.append('restaurant_id', restaurantId);

            btn.disabled = true;
            alert.className = 'alert';
            alert.style.display = 'none';

            try {
                const res = await fetch('https://restrogrow.com/sujay/api.php?action=addMenuItem', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    alert.className = 'alert alert-success show';
                    alert.textContent = data.message || 'Menu item added successfully!';
                    form.reset();
                    loadMenus();
                } else {
                    alert.className = 'alert alert-error show';
                    alert.textContent = data.message || 'Failed to add menu item';
                }
            } catch (e) {
                alert.className = 'alert alert-error show';
                alert.textContent = 'Error: ' + e.message;
            } finally {
                btn.disabled = false;
            }
        }

        async function createDemoData() {
            const btn = document.getElementById('demoBtn');
            const loading = document.getElementById('demoLoading');
            const alert = document.getElementById('demoAlert');
            
            btn.disabled = true;
            loading.classList.add('show');
            alert.className = 'alert';
            alert.style.display = 'none';

            try {
                const res = await fetch('https://restrogrow.com/sujay/api.php?action=createDemo', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ restaurant_id: restaurantId })
                });
                const data = await res.json();
                
                if (data.success) {
                    alert.className = 'alert alert-success show';
                    alert.innerHTML = '<strong>Demo data created successfully!</strong><br>' + 
                        (data.message || 'All demo items have been added.');
                    // Reload dropdowns
                    loadAreas();
                    loadMenus();
                } else {
                    alert.className = 'alert alert-error show';
                    alert.textContent = data.message || 'Failed to create demo data';
                }
            } catch (e) {
                alert.className = 'alert alert-error show';
                alert.textContent = 'Error: ' + e.message;
            } finally {
                btn.disabled = false;
                loading.classList.remove('show');
            }
        }
    </script>
</body>
</html>

