<?php
/**
 * Fix Currency Symbol Encoding
 * Run this once to fix corrupted currency symbols in database
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('default_charset', 'UTF-8');

// Set UTF-8 headers
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/db_connection.php';

echo "<pre style='font-family: Consolas, monospace; background: #1e1e1e; color: #0f0; padding: 20px;'>";
echo "===========================================\n";
echo "   FIX CURRENCY SYMBOL ENCODING\n";
echo "===========================================\n\n";

try {
    global $pdo;
    $conn = $pdo;
    
    // Currency symbol mappings (common corrupted patterns)
    $currency_fixes = [
        'Γé╣' => '₹',  // Indian Rupee corrupted
        'â‚¹' => '₹',
        'â€¹' => '₹',
        'Ã¢â€šÂ¹' => '₹',
        '$' => '$',    // Dollar (keep as is)
        '€' => '€',    // Euro
        '£' => '£',    // Pound
        '¥' => '¥',    // Yen
    ];
    
    // Get all users with currency symbols
    $stmt = $conn->query("SELECT id, username, restaurant_name, currency_symbol FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($users) . " users\n\n";
    
    $fixed = 0;
    foreach ($users as $user) {
        $old_symbol = $user['currency_symbol'] ?? '';
        $new_symbol = $old_symbol;
        
        // Check if symbol needs fixing
        if (!empty($old_symbol)) {
            // Try to fix common corruption patterns
            foreach ($currency_fixes as $corrupted => $correct) {
                if (strpos($old_symbol, $corrupted) !== false) {
                    $new_symbol = $correct;
                    break;
                }
            }
            
            // If still corrupted or empty, set default
            if (mb_strlen($new_symbol, 'UTF-8') > 1 || empty($new_symbol)) {
                $new_symbol = '₹'; // Default to Indian Rupee
            }
            
            // Update if changed
            if ($new_symbol !== $old_symbol) {
                $update_stmt = $conn->prepare("UPDATE users SET currency_symbol = ? WHERE id = ?");
                $update_stmt->execute([$new_symbol, $user['id']]);
                
                echo "✓ Fixed user #{$user['id']} ({$user['username']}):\n";
                echo "  Old: " . bin2hex($old_symbol) . " ('{$old_symbol}')\n";
                echo "  New: " . bin2hex($new_symbol) . " ('{$new_symbol}')\n\n";
                $fixed++;
            } else {
                echo "✓ User #{$user['id']} ({$user['username']}): OK ('{$old_symbol}')\n";
            }
        } else {
            // Set default if empty
            $update_stmt = $conn->prepare("UPDATE users SET currency_symbol = ? WHERE id = ?");
            $update_stmt->execute(['₹', $user['id']]);
            echo "✓ Set default currency for user #{$user['id']} ({$user['username']}): ₹\n";
            $fixed++;
        }
    }
    
    echo "\n===========================================\n";
    echo "   FIX COMPLETE!\n";
    echo "===========================================\n";
    echo "Fixed: $fixed users\n";
    echo "\n⚠️  DELETE THIS FILE AFTER RUNNING!\n";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "</pre>";
}
?>

