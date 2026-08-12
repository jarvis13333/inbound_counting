<?php

/**
 * Upgrade existing databases to the current schema (safe to run multiple times).
 */

function isMissingInEngineError(PDOException $e): bool
{
    $msg = $e->getMessage();
    return str_contains($msg, "doesn't exist in engine")
        || ($e->errorInfo[1] ?? 0) === 1932;
}

/** Drop tables that appear in SHOW TABLES but have no InnoDB data (MySQL error 1932). */
function repairOrphanedTables(PDO $db): void
{
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (!$tables) {
        return;
    }

    $broken = [];
    foreach ($tables as $table) {
        try {
            $db->query('SELECT 1 FROM `' . str_replace('`', '``', $table) . '` LIMIT 1');
        } catch (PDOException $e) {
            if (isMissingInEngineError($e)) {
                $broken[] = $table;
            } else {
                throw $e;
            }
        }
    }

    if (!$broken) {
        return;
    }

    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($broken as $table) {
        $db->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

function ensureDatabaseSchema(PDO $db): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    repairOrphanedTables($db);

    $usersTable = $db->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$usersTable) {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS users (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              username VARCHAR(50) NOT NULL UNIQUE,
              email VARCHAR(100) NOT NULL UNIQUE,
              password_hash VARCHAR(255) NOT NULL,
              role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
              reset_token VARCHAR(64) DEFAULT NULL,
              reset_token_expires DATETIME DEFAULT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB"
        );
    }

    $roleCol = $db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if (!$roleCol) {
        $db->exec(
            "ALTER TABLE users ADD COLUMN role ENUM('admin', 'user') NOT NULL DEFAULT 'user' AFTER password_hash"
        );
    }

    $resetTokenCol = $db->query("SHOW COLUMNS FROM users LIKE 'reset_token'")->fetch();
    if (!$resetTokenCol) {
        $db->exec(
            'ALTER TABLE users
             ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL,
             ADD COLUMN reset_token_expires DATETIME DEFAULT NULL'
        );
    }

    $hasAdmin = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($hasAdmin === 0) {
        $stmt = $db->prepare("UPDATE users SET role = 'admin' WHERE username = ?");
        $stmt->execute(['admin']);
        if ($stmt->rowCount() === 0) {
            $first = $db->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetch();
            if ($first) {
                $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute(['admin', $first['id']]);
            }
        }
    }

    $db->exec(
        'CREATE TABLE IF NOT EXISTS admin_shipments (
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
        ) ENGINE=InnoDB'
    );

    $adminPhotoCol = $db->query("SHOW COLUMNS FROM admin_shipments LIKE 'photo_path'")->fetch();
    if (!$adminPhotoCol) {
        $db->exec('ALTER TABLE admin_shipments ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL AFTER created_by');
    }

    $totalCartonCol = $db->query("SHOW COLUMNS FROM admin_shipments LIKE 'total_carton'")->fetch();
    if (!$totalCartonCol) {
        $db->exec(
            'ALTER TABLE admin_shipments ADD COLUMN total_carton INT UNSIGNED NOT NULL DEFAULT 0 AFTER shipment_number'
        );
    }

    $db->exec(
        'CREATE TABLE IF NOT EXISTS user_count_records (
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
        ) ENGINE=InnoDB'
    );

    $photoCol = $db->query("SHOW COLUMNS FROM user_count_records LIKE 'photo_path'")->fetch();
    if (!$photoCol) {
        $db->exec('ALTER TABLE user_count_records ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL AFTER remarks');
    }

    $boxCountCol = $db->query("SHOW COLUMNS FROM user_count_records LIKE 'box_count'")->fetch();
    if (!$boxCountCol) {
        $db->exec(
            'ALTER TABLE user_count_records ADD COLUMN box_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_quantity'
        );
    }
}
