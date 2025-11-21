<?php
require_once __DIR__ . '/auth.php';
require_superadmin();
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Superadmin Dashboard</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
  <script src="../assets/js/sweetalert2.all.min.js"></script>
  <style>
    :root{ --bg:#f4f6fb; --card:#fff; --border:#e5e7eb; --text:#111827; --muted:#6b7280; --primary:#151A2D; --green:#10b981; --red:#ef4444; --orange:#f59e0b; }
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:Inter,system-ui,Arial;background:var(--bg);color:var(--text);}
    .layout{display:flex;min-height:100vh;}
    .sidebar{width:260px;background:var(--primary);color:#fff;overflow-y:auto;position:sticky;top:0;height:100vh;}
    .sidebar-header{padding:20px;border-bottom:1px solid rgba(255,255,255,.1);}
    .sidebar-header h2{font-size:1.25rem;font-weight:700;}
    .sidebar-menu{padding:10px 0;}
    .menu-item{padding:12px 20px;cursor:pointer;display:flex;align-items:center;gap:12px;transition:background 0.2s;}
    .menu-item:hover{background:rgba(255,255,255,.1);}
    .menu-item.active{background:rgba(255,255,255,.15);border-left:3px solid #10b981;}
    .menu-item .icon{font-size:20px;}
    .menu-item .text{flex:1;font-weight:500;}
    .main-content{flex:1;padding:20px;overflow-x:hidden;}
    .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;}
    .page-header h1{font-size:1.75rem;font-weight:700;}
    .header-actions{display:flex;align-items:center;gap:12px;}
    .header-user{color:var(--muted);font-size:0.9rem;}
    .btn{padding:10px 16px;border:none;border-radius:8px;cursor:pointer;font-weight:600;transition:all 0.2s;}
    .btn-primary{background:var(--primary);color:#fff;}
    .btn-primary:hover{background:#0f141f;}
    .btn-outline{background:#fff;border:2px solid var(--border);color:var(--text);}
    .btn-outline:hover{background:#f9fafb;}
    .card{background:var(--card);border:1px solid var(--border);border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:20px;}
    .card-header{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border);}
    .card-body{padding:20px;}
    .card-title{font-weight:700;font-size:1.1rem;}
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;}
    .stat-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;}
    .stat-label{color:var(--muted);font-size:0.875rem;margin-bottom:8px;}
    .stat-value{font-size:2rem;font-weight:800;color:var(--primary);}
    table{width:100%;border-collapse:collapse;font-size:0.95rem;}
    th,td{padding:12px;border-bottom:1px solid var(--border);text-align:left;}
    th{background:#f9fafb;color:#374151;text-transform:uppercase;font-size:0.8rem;font-weight:600;}
    .badge{padding:4px 12px;border-radius:999px;font-weight:600;font-size:0.8rem;display:inline-block;}
    .badge-success{background:#d1fae5;color:#065f46;}
    .badge-warning{background:#fde68a;color:#92400e;}
    .badge-danger{background:#fee2e2;color:#991b1b;}
    .badge-info{background:#dbeafe;color:#1e40af;}
    input,select{padding:10px;border:2px solid var(--border);border-radius:8px;width:100%;}
    .form-group{margin-bottom:16px;}
    .form-group label{display:block;margin-bottom:6px;font-weight:600;font-size:0.9rem;}
    .page{display:none;}
    .page.active{display:block;}
    .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:1000;}
    .modal.active{display:flex;}
    .modal-content{background:#fff;border-radius:12px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;}
    .row{display:flex;gap:10px;flex-wrap:wrap;}
    @media (max-width:768px){
      .sidebar{width:70px;}
      .menu-item .text{display:none;}
      .stats-grid{grid-template-columns:1fr;}
    }
  </style>
</head>
<body>
  <div class="layout">
    <!-- Sidebar -->
    <div class="sidebar">
      <div class="sidebar-header">
        <h2>Superadmin</h2>
      </div>
      <div class="sidebar-menu">
        <div class="menu-item active" data-page="dashboard">
          <span class="material-symbols-rounded icon">dashboard</span>
          <span class="text">Dashboard</span>
        </div>
        <div class="menu-item" data-page="restaurants">
          <span class="material-symbols-rounded icon">restaurant</span>
          <span class="text">Restaurants</span>
        </div>
        <div class="menu-item" data-page="subscriptions">
          <span class="material-symbols-rounded icon">payments</span>
          <span class="text">Subscriptions</span>
        </div>
        <div class="menu-item" data-page="payments">
          <span class="material-symbols-rounded icon">receipt_long</span>
          <span class="text">Payments</span>
        </div>
        <div class="menu-item" data-page="analytics">
          <span class="material-symbols-rounded icon">analytics</span>
          <span class="text">Analytics</span>
        </div>
        <div class="menu-item" data-page="settings">
          <span class="material-symbols-rounded icon">settings</span>
          <span class="text">Settings</span>
        </div>
      </div>
      <div style="padding:20px;border-top:1px solid rgba(255,255,255,.1);margin-top:auto;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
          <span class="material-symbols-rounded">person</span>
          <span style="flex:1;"><?php echo htmlspecialchars($_SESSION['superadmin_username'] ?? ''); ?></span>
        </div>
        <a href="logout.php" class="btn btn-outline" style="width:100%;background:rgba(255,255,255,.1);color:#fff;border-color:rgba(255,255,255,.2);">Logout</a>
      </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
      <!-- Dashboard Page -->
      <div id="dashboardPage" class="page active">
        <div class="page-header">
          <h1>Dashboard Overview</h1>
          <div class="header-actions">
            <span class="header-user">Welcome, <?php echo htmlspecialchars($_SESSION['superadmin_username'] ?? ''); ?></span>
          </div>
        </div>
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-label">Total Restaurants</div>
            <div class="stat-value" id="statRestaurants">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Active Restaurants</div>
            <div class="stat-value" id="statActive">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Total Revenue (Today)</div>
            <div class="stat-value" id="statRevenue">₹0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Total Orders (Today)</div>
            <div class="stat-value" id="statOrders">0</div>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">Recent Activity</div>
          </div>
          <div class="card-body">
            <div id="recentActivity">Loading...</div>
          </div>
        </div>
      </div>

      <!-- Restaurants Page -->
      <div id="restaurantsPage" class="page">
        <div class="page-header">
          <h1>Restaurant Management</h1>
          <div class="header-actions">
            <button class="btn btn-outline" id="btnExport">Export CSV</button>
            <button class="btn btn-primary" id="btnNew">New Restaurant</button>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">All Restaurants</div>
            <input id="saSearch" placeholder="Search restaurants..." style="width:300px;">
          </div>
          <div class="card-body">
            <div style="overflow-x:auto;">
              <table id="restaurantsTable">
                <thead>
                  <tr>
                    <th>ID</th><th>Username</th><th>Restaurant ID</th><th>Name</th><th>Trial Status</th><th>Status</th><th>Created</th><th>Actions</th>
                  </tr>
                </thead>
                <tbody id="restaurantsTbody"></tbody>
              </table>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;">
              <button class="btn btn-outline" id="prevPage">Prev</button>
              <div id="pageInfo" style="color:var(--muted)">Page 1</div>
              <button class="btn btn-outline" id="nextPage">Next</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Subscriptions Page -->
      <div id="subscriptionsPage" class="page">
        <div class="page-header">
          <h1>Subscription Management</h1>
        </div>
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-label">Active Subscriptions</div>
            <div class="stat-value" id="subActive">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Trial Users</div>
            <div class="stat-value" id="subTrial">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Expired</div>
            <div class="stat-value" id="subExpired">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Total Revenue (Monthly)</div>
            <div class="stat-value" id="subRevenue">₹0</div>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">Subscription Details</div>
          </div>
          <div class="card-body">
            <div style="overflow-x:auto;">
              <table>
                <thead>
                  <tr>
                    <th>Restaurant</th><th>Status</th><th>Trial Ends</th><th>Renewal Date</th><th>Actions</th>
                  </tr>
                </thead>
                <tbody id="subscriptionsTbody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Payments Page -->
      <div id="paymentsPage" class="page">
        <div class="page-header">
          <h1>Payment History</h1>
        </div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">All Payments</div>
            <div class="row">
              <input id="paymentSearch" placeholder="Search..." style="width:200px;">
              <select id="paymentStatusFilter" style="width:150px;">
                <option value="">All Status</option>
                <option value="success">Success</option>
                <option value="failed">Failed</option>
                <option value="pending">Pending</option>
              </select>
            </div>
          </div>
          <div class="card-body">
            <div style="overflow-x:auto;">
              <table>
                <thead>
                  <tr>
                    <th>Transaction ID</th><th>Restaurant</th><th>Amount</th><th>Type</th><th>Status</th><th>Date</th>
                  </tr>
                </thead>
                <tbody id="paymentsTbody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Analytics Page -->
      <div id="analyticsPage" class="page">
        <div class="page-header">
          <h1>Analytics & Reports</h1>
        </div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">Revenue Overview</div>
          </div>
          <div class="card-body">
            <div id="revenueChart" style="height:300px;display:flex;align-items:center;justify-content:center;color:var(--muted);">
              Revenue chart will be displayed here
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">Restaurant Growth</div>
          </div>
          <div class="card-body">
            <div id="growthChart" style="height:300px;display:flex;align-items:center;justify-content:center;color:var(--muted);">
              Growth chart will be displayed here
            </div>
          </div>
        </div>
      </div>

      <!-- Settings Page -->
      <div id="settingsPage" class="page">
        <div class="page-header">
          <h1>Settings</h1>
        </div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">System Settings</div>
          </div>
          <div class="card-body">
            <div class="form-group">
              <label>Trial Period (Days)</label>
              <input type="number" id="trialDays" value="7" min="1" max="365">
            </div>
            <div class="form-group">
              <label>Subscription Price (₹)</label>
              <input type="number" id="subscriptionPrice" value="999" min="1">
            </div>
            <div class="form-group">
              <label>Auto-renewal</label>
              <select id="autoRenewal">
                <option value="1">Enabled</option>
                <option value="0">Disabled</option>
              </select>
            </div>
            <button class="btn btn-primary" id="saveSettings">Save Settings</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Create Restaurant Modal -->
  <div id="createModal" class="modal">
    <div class="modal-content">
      <div class="card-header">
        <div class="card-title">Create New Restaurant</div>
        <button id="cmClose" class="btn btn-outline">Close</button>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>Restaurant Name</label>
          <input id="cmName" placeholder="Enter restaurant name">
        </div>
        <div class="form-group">
          <label>Admin Username</label>
          <input id="cmUser" placeholder="Enter username">
        </div>
        <div class="form-group">
          <label>Admin Password</label>
          <input id="cmPass" type="password" placeholder="Enter password">
        </div>
        <small style="color:var(--muted);display:block;margin-bottom:16px;">Restaurant ID will be generated automatically.</small>
        <button id="cmCreate" class="btn btn-primary">Create Restaurant</button>
      </div>
    </div>
  </div>

  <script>
    // Navigation
    document.querySelectorAll('.menu-item').forEach(item => {
      item.addEventListener('click', function() {
        document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        const pageId = this.getAttribute('data-page') + 'Page';
        document.getElementById(pageId)?.classList.add('active');
        // Load page-specific data
        if(pageId === 'restaurantsPage') fetchRestaurants();
        else if(pageId === 'subscriptionsPage') loadSubscriptions();
        else if(pageId === 'paymentsPage') loadPayments();
        else if(pageId === 'dashboardPage') { loadStats(); loadRecentActivity(); }
      });
    });

    // Global variables
    let saPage = 1, saLimit = 10, saQuery = '';
    let paymentSearchTerm = '', paymentStatus = '';
    const showSuperAlert = (message, type = 'info') => {
      if (window.Swal) {
        Swal.fire({
          icon: type,
          text: message,
          confirmButtonColor: '#111827'
        });
      } else {
        alert(message);
      }
    };
    const showSuperPrompt = async (message, title = 'Input', defaultValue = '') => {
      if (window.Swal) {
        const { value } = await Swal.fire({
          title: title,
          text: message,
          input: 'text',
          inputValue: defaultValue,
          showCancelButton: true,
          confirmButtonColor: '#111827',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'OK',
          cancelButtonText: 'Cancel',
          inputValidator: (value) => {
            if (!value) {
              return 'Please enter a value';
            }
          }
        });
        return value || null;
      }
      return window.prompt(message, defaultValue);
    };

    // Dashboard Stats
    async function loadStats(){
      try {
        const res = await fetch('api.php?action=getStats');
        const d = await res.json();
        if(d.success){
          document.getElementById('statRestaurants').textContent = d.stats.restaurants;
          document.getElementById('statActive').textContent = d.stats.active;
          document.getElementById('statRevenue').textContent = '₹' + Number(d.stats.todayRevenue).toLocaleString('en-IN');
          document.getElementById('statOrders').textContent = d.stats.todayOrders;
        }
      } catch(e) { console.error('Error loading stats:', e); }
    }

    // Recent Activity
    async function loadRecentActivity(){
      try {
        const res = await fetch('api.php?action=getRestaurants&limit=5');
        const d = await res.json();
        if(d.success && d.restaurants){
          const html = d.restaurants.map(r => `
            <div style="padding:12px;border-bottom:1px solid var(--border);">
              <div style="font-weight:600;">${r.restaurant_name}</div>
              <div style="color:var(--muted);font-size:0.875rem;">Created on ${r.created_at?.split(' ')[0]}</div>
            </div>
          `).join('');
          document.getElementById('recentActivity').innerHTML = html || '<div style="padding:20px;text-align:center;color:var(--muted);">No recent activity</div>';
        }
      } catch(e) { console.error('Error loading activity:', e); }
    }

    // Restaurants
    async function fetchRestaurants(){
      try {
        const qs = `action=getRestaurants&page=${saPage}&limit=${saLimit}${saQuery?`&q=${encodeURIComponent(saQuery)}`:''}`;
        const res = await fetch('api.php?'+qs);
        const data = await res.json();
        const tbody = document.getElementById('restaurantsTbody');
        tbody.innerHTML = (data.restaurants||[]).map(r => {
          const isActive = Number(r.is_active) === 1;
          const trialStatus = r.trial_status;
          return `
            <tr>
              <td>${r.id}</td>
              <td>${r.username}</td>
              <td><span class="badge badge-info">${r.restaurant_id}</span></td>
              <td>${r.restaurant_name}</td>
              <td>${trialStatus === 'Active' ? `<span class="badge badge-success">${r.days_left} days</span>` : 
                  trialStatus === 'Disabled' ? `<span class="badge badge-warning">Disabled</span>` : 
                  `<span class="badge badge-danger">Expired</span>`}</td>
              <td>${isActive ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'}</td>
              <td>${r.created_at?.split(' ')[0] || ''}</td>
              <td style="display:flex;gap:8px;flex-wrap:wrap;">
                <button class="btn btn-outline" onclick="toggleRestaurant(${r.id}, 1)" ${isActive ? 'disabled' : ''}>Enable</button>
                <button class="btn btn-outline" onclick="toggleRestaurant(${r.id}, 0)" ${isActive ? '' : 'disabled'}>Disable</button>
                <button class="btn btn-outline" onclick="resetPassword(${r.id})">Reset PW</button>
              </td>
            </tr>
          `;
        }).join('');
        const total = data.total||0;
        const pages = Math.max(1, Math.ceil(total/saLimit));
        document.getElementById('pageInfo').textContent = `Page ${saPage} of ${pages}`;
        document.getElementById('prevPage').disabled = saPage<=1;
        document.getElementById('nextPage').disabled = saPage>=pages;
      } catch(e) { console.error('Error:', e); }
    }

    // Subscriptions
    async function loadSubscriptions(){
      try {
        const res = await fetch('api.php?action=getRestaurants&limit=100');
        const d = await res.json();
        if(d.success){
          const restaurants = (d.restaurants || []).map(r => ({
            ...r,
            isActive: Number(r.is_active) === 1
          }));
          const trial = restaurants.filter(r => r.trial_status === 'Active').length;
          const active = restaurants.filter(r => r.isActive && r.trial_status === 'Active').length;
          const expired = restaurants.filter(r => r.trial_status === 'Expired').length;
          
          document.getElementById('subActive').textContent = active;
          document.getElementById('subTrial').textContent = trial;
          document.getElementById('subExpired').textContent = expired;
          document.getElementById('subRevenue').textContent = '₹' + (999 * active).toLocaleString('en-IN');
          
          const tbody = document.getElementById('subscriptionsTbody');
          tbody.innerHTML = restaurants.map(r => `
            <tr>
              <td>${r.restaurant_name} (${r.restaurant_id})</td>
              <td>${r.trial_status === 'Active' ? '<span class="badge badge-success">Active</span>' : 
                  r.trial_status === 'Expired' ? '<span class="badge badge-danger">Expired</span>' : 
                  '<span class="badge badge-warning">Disabled</span>'}</td>
              <td>${r.trial_end_date || 'N/A'}</td>
              <td>${r.renewal_date || 'N/A'}</td>
              <td style="display:flex;gap:8px;flex-wrap:wrap;">
                <button class="btn btn-outline" onclick="toggleRestaurant(${r.id}, 1)" ${r.isActive ? 'disabled' : ''}>Enable</button>
                <button class="btn btn-outline" onclick="toggleRestaurant(${r.id}, 0)" ${r.isActive ? '' : 'disabled'}>Disable</button>
              </td>
            </tr>
          `).join('');
        }
      } catch(e) { console.error('Error loading subscriptions:', e); }
    }

    // Payments
    async function loadPayments(){
      try {
        const params = new URLSearchParams();
        if (paymentSearchTerm) params.append('search', paymentSearchTerm);
        if (paymentStatus) params.append('status', paymentStatus);
        const query = params.toString();
        const res = await fetch(`api.php?action=getPayments${query ? '&'+query : ''}`);
        const d = await res.json();
        if(d.success){
          const tbody = document.getElementById('paymentsTbody');
          tbody.innerHTML = (d.payments || []).map(p => `
            <tr>
              <td>${p.transaction_id || 'N/A'}</td>
              <td>${p.restaurant_name || p.restaurant_id || 'N/A'}</td>
              <td>₹${Number(p.amount).toLocaleString('en-IN')}</td>
              <td>${p.payment_method || 'N/A'}</td>
              <td>${p.payment_status === 'Success' ? '<span class="badge badge-success">Success</span>' : 
                  p.payment_status === 'Failed' ? '<span class="badge badge-danger">Failed</span>' : 
                  '<span class="badge badge-warning">Pending</span>'}</td>
              <td>${p.created_at ? new Date(p.created_at).toLocaleDateString() : 'N/A'}</td>
            </tr>
          `).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--muted);">No payments found</td></tr>';
        }
      } catch(e) { 
        document.getElementById('paymentsTbody').innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--muted);">Error loading payments</td></tr>';
        console.error('Error loading payments:', e); 
      }
    }

    // Create Restaurant Modal
    const createModal = document.getElementById('createModal');
    document.getElementById('btnNew').addEventListener('click', () => createModal.classList.add('active'));
    document.getElementById('cmClose').addEventListener('click', () => createModal.classList.remove('active'));
    document.getElementById('cmCreate').addEventListener('click', async () => {
      const payload = {
        username: document.getElementById('cmUser').value.trim(),
        password: document.getElementById('cmPass').value,
        restaurant_name: document.getElementById('cmName').value.trim(),
      };
      if(!payload.username || !payload.password || !payload.restaurant_name){ showSuperAlert('All fields are required'); return; }
      const res = await fetch('api.php?action=createRestaurant', { method:'POST', body: JSON.stringify(payload), headers: {'Content-Type': 'application/json'} });
      const data = await res.json();
      if (data.success) { 
        createModal.classList.remove('active');
        document.getElementById('cmUser').value = '';
        document.getElementById('cmPass').value = '';
        document.getElementById('cmName').value = '';
        fetchRestaurants(); 
        showSuperAlert('Created. Restaurant ID: '+data.restaurant_id); 
      } else showSuperAlert(data.message||'Error');
    });

    // Restaurant actions
    window.toggleRestaurant = async function(id, active){
      const res = await fetch('api.php?action=toggleRestaurant', { method:'POST', body: JSON.stringify({id, is_active: active}), headers: {'Content-Type': 'application/json'} });
      const data = await res.json();
      if (data.success) { fetchRestaurants(); if(document.getElementById('subscriptionsPage').classList.contains('active')) loadSubscriptions(); }
      else showSuperAlert(data.message||'Error');
    }

    window.resetPassword = async function(id){
      const p = await showSuperPrompt('New password for user id '+id+':', 'Reset Password');
      if(!p) return;
      const res = await fetch('api.php?action=resetPassword', { method:'POST', body: JSON.stringify({id, password: p}), headers: {'Content-Type': 'application/json'} });
      const data = await res.json();
      if (data.success) {
        showSuperAlert('Password reset successfully','success');
      } else {
        showSuperAlert(data.message||'Error','error');
      }
    }

    // Search and pagination
    document.getElementById('saSearch')?.addEventListener('input', (e)=>{ saQuery = e.target.value.trim(); saPage = 1; fetchRestaurants(); });
    document.getElementById('prevPage')?.addEventListener('click', ()=>{ if(saPage>1){ saPage--; fetchRestaurants(); }});
        document.getElementById('nextPage')?.addEventListener('click', ()=>{ saPage++; fetchRestaurants(); });
        document.getElementById('paymentSearch')?.addEventListener('input', (e)=>{ paymentSearchTerm = e.target.value.trim(); loadPayments(); });
        document.getElementById('paymentStatusFilter')?.addEventListener('change', (e)=>{ paymentStatus = e.target.value; loadPayments(); });
    document.getElementById('btnExport')?.addEventListener('click', ()=>{
      const rows = Array.from(document.querySelectorAll('#restaurantsTbody tr')).map(tr=>Array.from(tr.children).slice(0,6).map(td=>`"${td.textContent}"`).join(','));
      const header = 'ID,Username,Restaurant ID,Name,Status,Created';
      const csv = [header, ...rows].join('\n');
      const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
      const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'restaurants.csv'; a.click();
    });

    // Initialize
    loadStats();
    loadRecentActivity();
    fetchRestaurants();
  </script>
</body>
</html>
