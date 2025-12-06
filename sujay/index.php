<?php
/**
 * Sujay Testing Dashboard
 * Quick access to all important testing tools and connections
 */

require_once __DIR__ . '/../db_connection.php';

// Create superadmin user "sujay" if it doesn't exist
try {
    $checkStmt = $pdo->prepare("SELECT id FROM super_admins WHERE username = 'sujay' LIMIT 1");
    $checkStmt->execute();
    $existing = $checkStmt->fetch();
    
    if (!$existing) {
        // Create superadmin user "sujay" with password "sujay123"
        $passwordHash = password_hash('sujay123', PASSWORD_DEFAULT);
        $insertStmt = $pdo->prepare("INSERT INTO super_admins (username, email, password_hash, is_active) VALUES ('sujay', 'sujay@restrogrow.com', ?, 1)");
        $insertStmt->execute([$passwordHash]);
        $userCreated = true;
    } else {
        $userCreated = false;
    }
} catch (Exception $e) {
    $userError = $e->getMessage();
}

// Test database connection
$dbStatus = 'success';
$dbMessage = 'Connected successfully';
try {
    $testQuery = $pdo->query("SELECT 1");
    $testQuery->fetch();
} catch (Exception $e) {
    $dbStatus = 'error';
    $dbMessage = $e->getMessage();
}

// Get connection stats
$connectionStats = getConnectionStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sujay Testing Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            text-align: center;
        }
        .header h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
            font-size: 1.1em;
        }
        .status-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .status-card h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.5em;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .status-item:last-child {
            border-bottom: none;
        }
        .status-label {
            font-weight: 600;
            color: #555;
        }
        .status-value {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-success {
            background: #10b981;
            color: white;
        }
        .status-error {
            background: #ef4444;
            color: white;
        }
        .status-info {
            background: #3b82f6;
            color: white;
        }
        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .link-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .link-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .link-card h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.3em;
        }
        .link-card p {
            color: #666;
            font-size: 0.95em;
            line-height: 1.5;
        }
        .link-card .icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .category {
            margin-top: 40px;
        }
        .category-title {
            color: white;
            font-size: 1.8em;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .credentials-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border-left: 4px solid #667eea;
        }
        .credentials-box strong {
            color: #333;
        }
        .credentials-box code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Sujay Testing Dashboard</h1>
            <p>Quick access to all testing tools and connections</p>
        </div>

        <!-- Database Connection Status -->
        <div class="status-card">
            <h2>📊 Database Connection Status</h2>
            <div class="status-item">
                <span class="status-label">Connection Status:</span>
                <span class="status-value <?php echo $dbStatus === 'success' ? 'status-success' : 'status-error'; ?>">
                    <?php echo $dbStatus === 'success' ? '✓ Connected' : '✗ Error'; ?>
                </span>
            </div>
            <div class="status-item">
                <span class="status-label">Message:</span>
                <span class="status-value status-info"><?php echo htmlspecialchars($dbMessage); ?></span>
            </div>
            <div class="status-item">
                <span class="status-label">Connection Attempts:</span>
                <span class="status-value status-info"><?php echo $connectionStats['attempts']; ?></span>
            </div>
            <div class="status-item">
                <span class="status-label">Successful Connections:</span>
                <span class="status-value status-success"><?php echo $connectionStats['success']; ?></span>
            </div>
            <div class="status-item">
                <span class="status-label">Failed Connections:</span>
                <span class="status-value status-error"><?php echo $connectionStats['failures']; ?></span>
            </div>
        </div>

        <!-- Superadmin User Status -->
        <div class="status-card">
            <h2>👤 Superadmin User Status</h2>
            <?php if (isset($userCreated) && $userCreated): ?>
                <div class="status-item">
                    <span class="status-label">User Created:</span>
                    <span class="status-value status-success">✓ Created Successfully</span>
                </div>
            <?php elseif (isset($userCreated) && !$userCreated): ?>
                <div class="status-item">
                    <span class="status-label">User Status:</span>
                    <span class="status-value status-info">✓ Already Exists</span>
                </div>
            <?php endif; ?>
            <?php if (isset($userError)): ?>
                <div class="status-item">
                    <span class="status-label">Error:</span>
                    <span class="status-value status-error"><?php echo htmlspecialchars($userError); ?></span>
                </div>
            <?php endif; ?>
            <div class="credentials-box">
                <strong>Login Credentials:</strong><br>
                Username: <code>sujay</code><br>
                Password: <code>sujay123</code><br>
                <a href="../superadmin/login.php" style="color: #667eea; text-decoration: none; font-weight: 600;">→ Go to Superadmin Login</a>
            </div>
        </div>

        <!-- Testing Tools -->
        <div class="category">
            <h2 class="category-title">🧪 Testing Tools</h2>
            <div class="links-grid">
                <a href="../test_image_loading.php" class="link-card">
                    <div class="icon">🖼️</div>
                    <h3>Image Loading Test</h3>
                    <p>Test image loading from database and file system. Check uploads directory and image paths.</p>
                </a>

                <a href="../db_connection.php" class="link-card" target="_blank">
                    <div class="icon">🔌</div>
                    <h3>Database Connection</h3>
                    <p>View database connection configuration and test connection status.</p>
                </a>

                <a href="../admin/test_email.php" class="link-card">
                    <div class="icon">📧</div>
                    <h3>Email Test</h3>
                    <p>Test email sending functionality and SMTP configuration.</p>
                </a>

                <a href="../admin/connection_monitor.php" class="link-card">
                    <div class="icon">📡</div>
                    <h3>Connection Monitor</h3>
                    <p>Monitor database connections and view connection statistics.</p>
                </a>
            </div>
        </div>

        <!-- Important Files -->
        <div class="category">
            <h2 class="category-title">📁 Important Files</h2>
            <div class="links-grid">
                <a href="../config/session_config.php" class="link-card" target="_blank">
                    <div class="icon">🔐</div>
                    <h3>Session Config</h3>
                    <p>Session configuration and security settings.</p>
                </a>

                <a href="../config/email_config.php" class="link-card" target="_blank">
                    <div class="icon">⚙️</div>
                    <h3>Email Config</h3>
                    <p>Email server configuration and SMTP settings.</p>
                </a>

                <a href="../config/db_migration.php" class="link-card" target="_blank">
                    <div class="icon">🔄</div>
                    <h3>Database Migration</h3>
                    <p>Database migration scripts and schema updates.</p>
                </a>

                <a href="../superadmin/dashboard.php" class="link-card">
                    <div class="icon">📊</div>
                    <h3>Superadmin Dashboard</h3>
                    <p>Access the superadmin dashboard for managing restaurants.</p>
                </a>
            </div>
        </div>

        <!-- API Endpoints -->
        <div class="category">
            <h2 class="category-title">🔗 API Endpoints</h2>
            <div class="links-grid">
                <a href="../api/image.php" class="link-card" target="_blank">
                    <div class="icon">🖼️</div>
                    <h3>Image API</h3>
                    <p>Image serving endpoint for database and file-based images.</p>
                </a>

                <a href="../api/get_menu_items.php" class="link-card" target="_blank">
                    <div class="icon">🍽️</div>
                    <h3>Menu Items API</h3>
                    <p>Get menu items for restaurants.</p>
                </a>

                <a href="../api/get_dashboard_stats.php" class="link-card" target="_blank">
                    <div class="icon">📈</div>
                    <h3>Dashboard Stats API</h3>
                    <p>Get dashboard statistics and analytics.</p>
                </a>

                <a href="../superadmin/api.php" class="link-card" target="_blank">
                    <div class="icon">⚡</div>
                    <h3>Superadmin API</h3>
                    <p>Superadmin API endpoints for restaurant management.</p>
                </a>
            </div>
        </div>

        <!-- Documentation -->
        <div class="category">
            <h2 class="category-title">📚 Documentation</h2>
            <div class="links-grid">
                <a href="../docs/DATABASE_CONNECTION_OPTIMIZATION.md" class="link-card" target="_blank">
                    <div class="icon">📖</div>
                    <h3>Database Connection Docs</h3>
                    <p>Documentation on database connection optimization.</p>
                </a>

                <a href="../docs/IMAGE_STORAGE_OPTIONS.md" class="link-card" target="_blank">
                    <div class="icon">📸</div>
                    <h3>Image Storage Docs</h3>
                    <p>Documentation on image storage options and implementation.</p>
                </a>

                <a href="../DEPLOYMENT.md" class="link-card" target="_blank">
                    <div class="icon">🚀</div>
                    <h3>Deployment Guide</h3>
                    <p>Deployment instructions and server configuration.</p>
                </a>

                <a href="../database/database_schema.sql" class="link-card" target="_blank">
                    <div class="icon">🗄️</div>
                    <h3>Database Schema</h3>
                    <p>Complete database schema and table structures.</p>
                </a>
            </div>
        </div>
    </div>
</body>
</html>

