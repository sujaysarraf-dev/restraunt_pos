<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migrations - Restaurant Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            min-height: 100vh;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .content {
            padding: 2rem;
        }
        
        .status {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .status.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .status.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .status.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .migration-item {
            padding: 1rem;
            margin-bottom: 0.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        
        .migration-item.failed {
            border-left-color: #dc3545;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .error-list {
            background: #f8d7da;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .error-item {
            color: #721c24;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗄️ Database Migrations</h1>
            <p>Automatically apply SQL updates to your database</p>
        </div>
        
        <div class="content">
            <div id="statusMessages"></div>
            <div id="results" style="display: none;">
                <div class="stats" id="stats">
                    <div class="stat-card">
                        <div class="stat-number" id="statTotal">0</div>
                        <div class="stat-label">Total Files</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="statRun">0</div>
                        <div class="stat-label">Migrations Run</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="statErrors">0</div>
                        <div class="stat-label">Errors</div>
                    </div>
                </div>
                <div id="migrationList"></div>
                <div id="errorList"></div>
                <a href="dashboard.php" style="display: block; margin-top: 2rem; text-align: center; color: #667eea; text-decoration: none; font-weight: 600;">← Back to Dashboard</a>
            </div>
            
            <button class="btn" id="runMigrationsBtn" onclick="runMigrations()">
                Run Migrations
            </button>
        </div>
    </div>

    <script>
        async function runMigrations() {
            const btn = document.getElementById('runMigrationsBtn');
            const statusDiv = document.getElementById('statusMessages');
            const resultsDiv = document.getElementById('results');
            const migrationList = document.getElementById('migrationList');
            
            // Show loading state
            btn.disabled = true;
            btn.textContent = 'Running Migrations...';
            
            statusDiv.innerHTML = '<div class="loading"><div class="spinner"></div><p>Running database migrations...</p></div>';
            
            try {
                const response = await fetch('db_migration.php');
                
                // Check if response is actually JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error('Expected JSON but got: ' + text.substring(0, 100));
                }
                
                const data = await response.json();
                
                if (data.success) {
                    // Update stats
                    document.getElementById('statTotal').textContent = data.total_files;
                    document.getElementById('statRun').textContent = data.migrations_run;
                    document.getElementById('statErrors').textContent = data.errors.length;
                    
                    // Show status message
                    if (data.migrations_run > 0) {
                        statusDiv.innerHTML = `<div class="status success">
                            <strong>Success!</strong> ${data.migrations_run} migration(s) executed successfully.
                        </div>`;
                    } else {
                        statusDiv.innerHTML = `<div class="status info">
                            <strong>Up to date!</strong> No new migrations to run.
                        </div>`;
                    }
                    
                    // Show results
                    resultsDiv.style.display = 'block';
                    migrationList.innerHTML = `<div class="migration-item">
                        <strong>Migration System Active</strong><br>
                        Total SQL files found: ${data.total_files}<br>
                        Migrations executed: ${data.migrations_run}
                    </div>`;
                    
                    // Show errors if any
                    if (data.errors && data.errors.length > 0) {
                        const errorListDiv = document.getElementById('errorList');
                        errorListDiv.innerHTML = '<div class="error-list"><strong>Errors:</strong>' +
                            data.errors.map(err => `<div class="error-item">${err}</div>`).join('') +
                            '</div>';
                    }
                    
                    btn.textContent = 'Run Again';
                    btn.disabled = false;
                } else {
                    statusDiv.innerHTML = `<div class="status error">
                        <strong>Error!</strong> ${data.error}
                    </div>`;
                    btn.textContent = 'Try Again';
                    btn.disabled = false;
                }
            } catch (error) {
                statusDiv.innerHTML = `<div class="status error">
                    <strong>Error!</strong> Failed to run migrations: ${error.message}
                </div>`;
                btn.textContent = 'Try Again';
                btn.disabled = false;
            }
        }
        
        // Auto-run on page load
        window.addEventListener('load', () => {
            setTimeout(() => {
                runMigrations();
            }, 500);
        });
    </script>
</body>
</html>

