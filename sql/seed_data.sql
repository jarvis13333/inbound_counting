-- Sample data for Product Inbound Shipment Counting Record
-- Import in phpMyAdmin (select database inbound_counting first) or:
--   mysql -u root inbound_counting < sql/seed_data.sql
--
-- Test accounts (passwords):
--   admin / admin123
--   alice / user123
--   bob   / user123
--   carol / user123

USE inbound_counting;

-- ---------------------------------------------------------------------------
-- Users (skip if username/email already exists)
-- ---------------------------------------------------------------------------
INSERT INTO users (username, email, password_hash, role) VALUES
('admin', 'admin@example.com',
 '$2y$10$BcMxQckDY/S3jT/ULG0yd.ndW03X1WH1TzSU47AnK0bmCRXY8IFJ.', 'admin'),
('alice', 'alice@warehouse.com',
 '$2y$10$qjHatjxwMGuviYwNEBJoZO1JRwgnSutJloLh.LvtNtG9ik..Fysvm', 'user'),
('bob', 'bob@warehouse.com',
 '$2y$10$qjHatjxwMGuviYwNEBJoZO1JRwgnSutJloLh.LvtNtG9ik..Fysvm', 'user'),
('carol', 'carol@warehouse.com',
 '$2y$10$qjHatjxwMGuviYwNEBJoZO1JRwgnSutJloLh.LvtNtG9ik..Fysvm', 'user')
ON DUPLICATE KEY UPDATE
  email = VALUES(email),
  password_hash = VALUES(password_hash),
  role = VALUES(role);

-- ---------------------------------------------------------------------------
-- Admin inbound shipments
-- Status preview:
--   SHIP-2026-001  counted, qty match   (green + blue)
--   SHIP-2026-002  counted, qty mismatch (green + orange)
--   SHIP-2026-003  not counted yet      (red)
--   SHIP-2026-004  today inbound        (filter: today)
--   SHIP-2026-005  5 days ago           (filter: past 7 days)
-- ---------------------------------------------------------------------------
INSERT INTO admin_shipments (inbound_date, product_name, shipment_number, total_quantity, created_by) VALUES
(DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Wireless Mouse M200', 'SHIP-2026-001', 100,
 (SELECT id FROM users WHERE username = 'admin' LIMIT 1)),
(DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'USB-C Hub 7-in-1', 'SHIP-2026-002', 200,
 (SELECT id FROM users WHERE username = 'admin' LIMIT 1)),
(CURDATE(), 'Bluetooth Keyboard K10', 'SHIP-2026-003', 150,
 (SELECT id FROM users WHERE username = 'admin' LIMIT 1)),
(CURDATE(), 'Laptop Stand Aluminum', 'SHIP-2026-004', 80,
 (SELECT id FROM users WHERE username = 'admin' LIMIT 1)),
(DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'HDMI Cable 2m', 'SHIP-2026-005', 500,
 (SELECT id FROM users WHERE username = 'admin' LIMIT 1))
ON DUPLICATE KEY UPDATE
  inbound_date = VALUES(inbound_date),
  product_name = VALUES(product_name),
  total_quantity = VALUES(total_quantity);

-- ---------------------------------------------------------------------------
-- User counting records
-- ---------------------------------------------------------------------------
INSERT INTO user_count_records
  (user_id, admin_shipment_id, shipment_number, product_name, counting_date,
   start_time, completion_time, total_quantity, counted_by, remarks)
VALUES
(
  (SELECT id FROM users WHERE username = 'alice' LIMIT 1),
  (SELECT id FROM admin_shipments WHERE shipment_number = 'SHIP-2026-001' LIMIT 1),
  'SHIP-2026-001', 'Wireless Mouse M200', DATE_SUB(CURDATE(), INTERVAL 1 DAY),
  '09:00:00', '10:30:00', 100, 'Alice Tan', 'Qty matches admin record'
),
(
  (SELECT id FROM users WHERE username = 'bob' LIMIT 1),
  (SELECT id FROM admin_shipments WHERE shipment_number = 'SHIP-2026-002' LIMIT 1),
  'SHIP-2026-002', 'USB-C Hub 7-in-1', CURDATE(),
  '14:00:00', '16:15:00', 180, 'Bob Lee', 'Short 20 units — pending recount'
),
(
  (SELECT id FROM users WHERE username = 'carol' LIMIT 1),
  (SELECT id FROM admin_shipments WHERE shipment_number = 'SHIP-2026-005' LIMIT 1),
  'SHIP-2026-005', 'HDMI Cable 2m', DATE_SUB(CURDATE(), INTERVAL 4 DAY),
  '08:30:00', '12:00:00', 500, 'Carol Wong', 'Completed in Zone B'
);

-- SHIP-2026-003 and SHIP-2026-004 intentionally have no user counts (red status)
