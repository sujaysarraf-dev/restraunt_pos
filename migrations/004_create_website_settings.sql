-- Website appearance settings per restaurant
CREATE TABLE IF NOT EXISTS website_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id VARCHAR(10) NOT NULL UNIQUE,
  primary_red VARCHAR(20) DEFAULT '#F70000',
  dark_red VARCHAR(20) DEFAULT '#DA020E',
  primary_yellow VARCHAR(20) DEFAULT '#FFD100',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ws_restaurant_id (restaurant_id)
);


