<?php
/**
 * Database Performance Testing Tool
 * Tests database speed, connections, queries, and performance metrics
 */

require_once __DIR__ . '/../db_connection.php';

// Only allow superadmin or admin
session_start();
if (!isset($_SESSION['superadmin_id']) && !isset($_SESSION['user_id'])) {
    die('Unauthorized. Please login first.');
}

header('Content-Type: text/html; charset=UTF-8');

// Test results storage
$testResults = [];

// Test 1: Connection Speed
function testConnectionSpeed($pdo) {
    $start = microtime(true);
    $pdo->query("SELECT 1");
    $end = microtime(true);
    return ($end - $start) * 1000; // Convert to milliseconds
}

// Test 2: Query Performance
function testQueryPerformance($pdo) {
    $results = [];
    
    // Test simple SELECT
    $start = microtime(true);
    $pdo->query("SELECT COUNT(*) FROM users");
    $results['simple_select'] = (microtime(true) - $start) * 1000;
    
    // Test JOIN query
    $start = microtime(true);
    $pdo->query("SELECT mi.*, m.menu_name FROM menu_items mi JOIN menu m ON mi.menu_id = m.id LIMIT 10");
    $results['join_query'] = (microtime(true) - $start) * 1000;
    
    // Test indexed query
    $start = microtime(true);
    $pdo->query("SELECT * FROM menu_items WHERE restaurant_id = 'RES001' AND is_available = 1 LIMIT 10");
    $results['indexed_query'] = (microtime(true) - $start) * 1000;
    
    // Test COUNT with WHERE
    $start = microtime(true);
    $pdo->query("SELECT COUNT(*) FROM orders WHERE restaurant_id = 'RES001' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $results['count_with_where'] = (microtime(true) - $start) * 1000;
    
    return $results;
}

// Test 3: Index Usage
function testIndexUsage($pdo) {
    $results = [];
    
    try {
        $stmt = $pdo->query("
            SELECT 
                TABLE_NAME,
                INDEX_NAME,
                GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS COLUMNS,
                CARDINALITY
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN ('users', 'menu_items', 'orders', 'menu', 'payments')
                AND INDEX_NAME LIKE 'idx_%'
            GROUP BY TABLE_NAME, INDEX_NAME
            ORDER BY TABLE_NAME, INDEX_NAME
        ");
        $results['indexes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results['count'] = count($results['indexes']);
    } catch (Exception $e) {
        $results['error'] = $e->getMessage();
    }
    
    return $results;
}

// Test 4: Table Sizes
function testTableSizes($pdo) {
    $results = [];
    
    try {
        $stmt = $pdo->query("
            SELECT 
                table_name AS 'Table',
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
                ROUND((data_length / 1024 / 1024), 2) AS 'Data (MB)',
                ROUND((index_length / 1024 / 1024), 2) AS 'Indexes (MB)',
                table_rows AS 'Rows'
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
            ORDER BY (data_length + index_length) DESC
        ");
        $results['tables'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $results['error'] = $e->getMessage();
    }
    
    return $results;
}

// Test 5: Slow Queries Check
function testSlowQueries($pdo) {
    $results = [];
    
    try {
        // Check if slow query log is enabled
        $stmt = $pdo->query("SHOW VARIABLES LIKE 'slow_query_log'");
        $slowLog = $stmt->fetch(PDO::FETCH_ASSOC);
        $results['slow_log_enabled'] = $slowLog['Value'] ?? 'OFF';
        
        // Get slow query time threshold
        $stmt = $pdo->query("SHOW VARIABLES LIKE 'long_query_time'");
        $longQueryTime = $stmt->fetch(PDO::FETCH_ASSOC);
        $results['long_query_time'] = $longQueryTime['Value'] ?? 'N/A';
        
        // Get slow query count
        $stmt = $pdo->query("SHOW STATUS LIKE 'Slow_queries'");
        $slowQueries = $stmt->fetch(PDO::FETCH_ASSOC);
        $results['slow_query_count'] = $slowQueries['Value'] ?? '0';
    } catch (Exception $e) {
        $results['error'] = $e->getMessage();
    }
    
    return $results;
}

// Test 6: Connection Status
function testConnectionStatus($pdo) {
    $results = [];
    
    try {
        // Current connections
        $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
        $threads = $stmt->fetch(PDO::FETCH_ASSOC);
        $results['current_connections'] = $threads['Value'] ?? '0';
        
        // Max connections
        $stmt = $pdo->query("SHOW VARIABLES LIKE 'max_connections'");
        $maxConn = $stmt->fetch(PDO::FETCH_ASSOC);
        $results['max_connections'] = $maxConn['Value'] ?? '0';
        
        // Max used connections
        $stmt = $pdo->query("SHOW STATUS LIKE 'Max_used_connections'");
        $maxUsed = $stmt->fetch(PDO::FETCH_ASSOC);
        $results['max_used_connections'] = $maxUsed['Value'] ?? '0';
        
        // Connection usage percentage
        if ($results['max_connections'] > 0) {
            $results['connection_usage_percent'] = round(($results['current_connections'] / $results['max_connections']) * 100, 2);
        }
    } catch (Exception $e) {
        $results['error'] = $e->getMessage();
    }
    
    return $results;
}

// Test 7: Buffer Pool Status
function testBufferPool($pdo) {
    $results = [];
    
    try {
        // InnoDB Buffer Pool Size
        $stmt = $pdo->query("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
        $bufferSize = $stmt->fetch(PDO::FETCH_ASSOC);
        $results['buffer_pool_size'] = $bufferSize['Value'] ?? '0';
        $results['buffer_pool_size_mb'] = round($results['buffer_pool_size'] / 1024 / 1024, 2);
        
        // Buffer Pool Read Requests
        $stmt = $pdo->query("SHOW STATUS LIKE 'Innodb_buffer_pool_read_requests'");
        $readReq = $stmt->fetch(PDO::FETCH_ASSOC);
        $results['read_requests'] = $readReq['Value'] ?? '0';
        
        // Buffer Pool Reads (from disk)
        $stmt = $pdo->query("SHOW STATUS LIKE 'Innodb_buffer_pool_reads'");
        $reads = $stmt->fetch(PDO::FETCH_ASSOC);
        $results['reads_from_disk'] = $reads['Value'] ?? '0';
        
        // Calculate hit rate
        if ($results['read_requests'] > 0) {
            $hitRate = (1 - ($results['reads_from_disk'] / $results['read_requests'])) * 100;
            $results['hit_rate_percent'] = round($hitRate, 2);
        }
    } catch (Exception $e) {
        $results['error'] = $e->getMessage();
    }
    
    return $results;
}

// Run all tests
$testResults['connection_speed'] = testConnectionSpeed($pdo);
$testResults['query_performance'] = testQueryPerformance($pdo);
$testResults['index_usage'] = testIndexUsage($pdo);
$testResults['table_sizes'] = testTableSizes($pdo);
$testResults['slow_queries'] = testSlowQueries($pdo);
$testResults['connection_status'] = testConnectionStatus($pdo);
$testResults['buffer_pool'] = testBufferPool($pdo);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Performance Testing</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e40af;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            padding: 2rem;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .header p {
            color: var(--gray-600);
        }

        .test-section {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .test-section h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--gray-200);
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .metric-card {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        .metric-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .metric-value.good {
            color: var(--success);
        }

        .metric-value.warning {
            color: var(--warning);
        }

        .metric-value.error {
            color: var(--error);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.875rem;
            text-transform: uppercase;
        }

        tr:hover {
            background: var(--gray-50);
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            margin-top: 1rem;
            transition: background 0.2s;
        }

        .btn:hover {
            background: #1e3a8a;
        }

        .performance-bar {
            height: 8px;
            background: var(--gray-200);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .performance-bar-fill {
            height: 100%;
            background: var(--success);
            transition: width 0.3s;
        }

        .performance-bar-fill.warning {
            background: var(--warning);
        }

        .performance-bar-fill.error {
            background: var(--error);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Database Performance Testing</h1>
            <p>Comprehensive database speed, performance, and health diagnostics</p>
        </div>

        <!-- Connection Speed Test -->
        <div class="test-section">
            <h2>⚡ Connection Speed</h2>
            <div class="metric-grid">
                <div class="metric-card">
                    <div class="metric-label">Connection Time</div>
                    <div class="metric-value <?php 
                        echo $testResults['connection_speed'] < 10 ? 'good' : ($testResults['connection_speed'] < 50 ? 'warning' : 'error'); 
                    ?>">
                        <?php echo number_format($testResults['connection_speed'], 2); ?> ms
                    </div>
                    <div class="performance-bar">
                        <div class="performance-bar-fill <?php 
                            echo $testResults['connection_speed'] < 10 ? '' : ($testResults['connection_speed'] < 50 ? 'warning' : 'error'); 
                        ?>" style="width: <?php echo min(100, ($testResults['connection_speed'] / 100) * 100); ?>%"></div>
                    </div>
                </div>
            </div>
            <p style="margin-top: 1rem; color: var(--gray-600); font-size: 0.875rem;">
                <?php if ($testResults['connection_speed'] < 10): ?>
                    ✓ Excellent connection speed
                <?php elseif ($testResults['connection_speed'] < 50): ?>
                    ⚠ Acceptable connection speed
                <?php else: ?>
                    ✗ Slow connection - may need optimization
                <?php endif; ?>
            </p>
        </div>

        <!-- Query Performance Test -->
        <div class="test-section">
            <h2>🚀 Query Performance</h2>
            <div class="metric-grid">
                <?php foreach ($testResults['query_performance'] as $queryName => $time): ?>
                    <div class="metric-card">
                        <div class="metric-label"><?php echo ucfirst(str_replace('_', ' ', $queryName)); ?></div>
                        <div class="metric-value <?php 
                            echo $time < 50 ? 'good' : ($time < 200 ? 'warning' : 'error'); 
                        ?>">
                            <?php echo number_format($time, 2); ?> ms
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Index Usage -->
        <div class="test-section">
            <h2>📊 Index Usage</h2>
            <?php if (isset($testResults['index_usage']['error'])): ?>
                <p style="color: var(--error);">Error: <?php echo htmlspecialchars($testResults['index_usage']['error']); ?></p>
            <?php else: ?>
                <div class="metric-card" style="margin-bottom: 1rem;">
                    <div class="metric-label">Total Critical Indexes</div>
                    <div class="metric-value <?php echo $testResults['index_usage']['count'] >= 11 ? 'good' : 'error'; ?>">
                        <?php echo $testResults['index_usage']['count']; ?> indexes
                    </div>
                </div>
                <?php if (!empty($testResults['index_usage']['indexes'])): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Table</th>
                                <th>Index Name</th>
                                <th>Columns</th>
                                <th>Cardinality</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($testResults['index_usage']['indexes'] as $index): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($index['TABLE_NAME']); ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($index['INDEX_NAME']); ?></code></td>
                                    <td><?php echo htmlspecialchars($index['COLUMNS']); ?></td>
                                    <td><?php echo number_format($index['CARDINALITY'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Table Sizes -->
        <div class="test-section">
            <h2>💾 Database Storage</h2>
            <?php if (isset($testResults['table_sizes']['error'])): ?>
                <p style="color: var(--error);">Error: <?php echo htmlspecialchars($testResults['table_sizes']['error']); ?></p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th>Total Size (MB)</th>
                            <th>Data (MB)</th>
                            <th>Indexes (MB)</th>
                            <th>Rows</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalSize = 0;
                        foreach ($testResults['table_sizes']['tables'] as $table): 
                            $totalSize += $table['Size (MB)'];
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($table['Table']); ?></strong></td>
                                <td><?php echo number_format($table['Size (MB)'], 2); ?></td>
                                <td><?php echo number_format($table['Data (MB)'], 2); ?></td>
                                <td><?php echo number_format($table['Indexes (MB)'], 2); ?></td>
                                <td><?php echo number_format($table['Rows']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--gray-50); font-weight: 600;">
                            <td><strong>Total</strong></td>
                            <td><strong><?php echo number_format($totalSize, 2); ?> MB</strong></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>

        <!-- Connection Status -->
        <div class="test-section">
            <h2>🔌 Connection Status</h2>
            <div class="metric-grid">
                <div class="metric-card">
                    <div class="metric-label">Current Connections</div>
                    <div class="metric-value"><?php echo $testResults['connection_status']['current_connections'] ?? 'N/A'; ?></div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Max Connections</div>
                    <div class="metric-value"><?php echo $testResults['connection_status']['max_connections'] ?? 'N/A'; ?></div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Max Used</div>
                    <div class="metric-value"><?php echo $testResults['connection_status']['max_used_connections'] ?? 'N/A'; ?></div>
                </div>
                <?php if (isset($testResults['connection_status']['connection_usage_percent'])): ?>
                    <div class="metric-card">
                        <div class="metric-label">Usage</div>
                        <div class="metric-value <?php 
                            $usage = $testResults['connection_status']['connection_usage_percent'];
                            echo $usage < 50 ? 'good' : ($usage < 80 ? 'warning' : 'error'); 
                        ?>">
                            <?php echo $usage; ?>%
                        </div>
                        <div class="performance-bar">
                            <div class="performance-bar-fill <?php 
                                echo $usage < 50 ? '' : ($usage < 80 ? 'warning' : 'error'); 
                            ?>" style="width: <?php echo $usage; ?>%"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Buffer Pool Status -->
        <div class="test-section">
            <h2>📦 Buffer Pool Status</h2>
            <?php if (isset($testResults['buffer_pool']['error'])): ?>
                <p style="color: var(--error);">Error: <?php echo htmlspecialchars($testResults['buffer_pool']['error']); ?></p>
            <?php else: ?>
                <div class="metric-grid">
                    <div class="metric-card">
                        <div class="metric-label">Buffer Pool Size</div>
                        <div class="metric-value"><?php echo number_format($testResults['buffer_pool']['buffer_pool_size_mb'] ?? 0, 2); ?> MB</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Read Requests</div>
                        <div class="metric-value"><?php echo number_format($testResults['buffer_pool']['read_requests'] ?? 0); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Reads from Disk</div>
                        <div class="metric-value"><?php echo number_format($testResults['buffer_pool']['reads_from_disk'] ?? 0); ?></div>
                    </div>
                    <?php if (isset($testResults['buffer_pool']['hit_rate_percent'])): ?>
                        <div class="metric-card">
                            <div class="metric-label">Hit Rate</div>
                            <div class="metric-value <?php 
                                $hitRate = $testResults['buffer_pool']['hit_rate_percent'];
                                echo $hitRate > 95 ? 'good' : ($hitRate > 80 ? 'warning' : 'error'); 
                            ?>">
                                <?php echo $hitRate; ?>%
                            </div>
                            <div class="performance-bar">
                                <div class="performance-bar-fill <?php 
                                    echo $hitRate > 95 ? '' : ($hitRate > 80 ? 'warning' : 'error'); 
                                ?>" style="width: <?php echo $hitRate; ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Slow Queries -->
        <div class="test-section">
            <h2>🐌 Slow Query Status</h2>
            <div class="metric-grid">
                <div class="metric-card">
                    <div class="metric-label">Slow Query Log</div>
                    <div class="metric-value">
                        <span class="badge <?php echo ($testResults['slow_queries']['slow_log_enabled'] ?? 'OFF') === 'ON' ? 'badge-success' : 'badge-warning'; ?>">
                            <?php echo $testResults['slow_queries']['slow_log_enabled'] ?? 'OFF'; ?>
                        </span>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Long Query Time</div>
                    <div class="metric-value"><?php echo $testResults['slow_queries']['long_query_time'] ?? 'N/A'; ?> sec</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Slow Queries Count</div>
                    <div class="metric-value <?php 
                        $slowCount = (int)($testResults['slow_queries']['slow_query_count'] ?? 0);
                        echo $slowCount === 0 ? 'good' : ($slowCount < 100 ? 'warning' : 'error'); 
                    ?>">
                        <?php echo number_format($slowCount); ?>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 2rem; text-align: center;">
            <a href="https://restrogrow.com/sujay/index.php" class="btn">← Back to Testing Dashboard</a>
            <button onclick="location.reload()" class="btn" style="background: var(--success); margin-left: 1rem;">🔄 Run Tests Again</button>
        </div>
    </div>
</body>
</html>

