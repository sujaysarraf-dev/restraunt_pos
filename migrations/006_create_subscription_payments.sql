-- Create subscription_payments table
CREATE TABLE IF NOT EXISTS subscription_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    restaurant_id VARCHAR(10) NOT NULL,
    transaction_id VARCHAR(100) NOT NULL UNIQUE,
    phonepe_transaction_id VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    subscription_type ENUM('monthly', 'yearly') DEFAULT 'monthly',
    payment_status ENUM('pending', 'success', 'failed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_user_id (user_id),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

