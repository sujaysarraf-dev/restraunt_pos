-- Create website_banners table to store multiple banners per restaurant
CREATE TABLE IF NOT EXISTS website_banners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id VARCHAR(50) NOT NULL,
  banner_path VARCHAR(255) NOT NULL,
  display_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_restaurant (restaurant_id),
  INDEX idx_order (restaurant_id, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

