-- Upgrade from old 60-session inbound app to Product Inbound Shipment Counting Record
-- Run in phpMyAdmin on your existing inbound_counting database

USE inbound_counting;

-- Skip next line if column `role` already exists
ALTER TABLE users
  ADD COLUMN role ENUM('admin', 'user') NOT NULL DEFAULT 'user' AFTER password_hash;

UPDATE users SET role = 'user' WHERE role IS NULL OR role = '';

DROP TABLE IF EXISTS inbound_records;

CREATE TABLE IF NOT EXISTS admin_shipments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inbound_date DATE NOT NULL,
  product_name VARCHAR(200) NOT NULL,
  shipment_number VARCHAR(100) NOT NULL,
  total_quantity INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_shipment_number (shipment_number),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_count_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  admin_shipment_id INT UNSIGNED DEFAULT NULL,
  shipment_number VARCHAR(100) NOT NULL,
  product_name VARCHAR(200) NOT NULL,
  counting_date DATE NOT NULL,
  start_time TIME DEFAULT NULL,
  completion_time TIME DEFAULT NULL,
  total_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  counted_by VARCHAR(100) NOT NULL,
  remarks TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (admin_shipment_id) REFERENCES admin_shipments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Optional: promote a user to admin (change username as needed)
-- UPDATE users SET role = 'admin' WHERE username = 'your_admin_name';
