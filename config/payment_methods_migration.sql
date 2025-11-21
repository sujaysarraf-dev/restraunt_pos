-- Payment Methods Table Migration
-- Run this SQL file to create the payment_methods table

CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id VARCHAR(50) NOT NULL,
    method_name VARCHAR(100) NOT NULL,
    emoji VARCHAR(10) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_restaurant_method (restaurant_id, method_name),
    INDEX idx_restaurant_id (restaurant_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default payment methods with emojis for all restaurants
-- Note: Replace 'RES001' with your actual restaurant IDs or run the PHP script instead

INSERT IGNORE INTO payment_methods (restaurant_id, method_name, emoji, display_order) VALUES
('RES001', 'Cash', '💵', 0),
('RES001', 'Card', '💳', 1),
('RES001', 'UPI', '📱', 2),
('RES001', 'Online', '🌐', 3),
('RES001', 'Wallet', '👛', 4),
('RES001', 'Bank Transfer', '🏦', 5),
('RES001', 'Cheque', '📝', 6),
('RES001', 'Cryptocurrency', '₿', 7);



