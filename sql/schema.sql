-- Product Inbound Shipment Counting Record System
-- Run in phpMyAdmin or: mysql -u root < sql/schema.sql

CREATE DATABASE IF NOT EXISTS inbound_counting
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE inbound_counting;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  reset_token VARCHAR(64) DEFAULT NULL,
  reset_token_expires DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_shipments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inbound_date DATE NOT NULL,
  product_name VARCHAR(200) NOT NULL,
  shipment_number VARCHAR(100) NOT NULL,
  total_carton INT UNSIGNED NOT NULL DEFAULT 0,
  total_quantity INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  photo_path VARCHAR(255) DEFAULT NULL,
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
  box_count INT UNSIGNED NOT NULL DEFAULT 0,
  counted_by VARCHAR(100) NOT NULL,
  remarks TEXT DEFAULT NULL,
  photo_path VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (admin_shipment_id) REFERENCES admin_shipments(id) ON DELETE SET NULL
) ENGINE=InnoDB;
