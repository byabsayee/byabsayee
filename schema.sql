-- =============================================================================
-- Byabsayee — Database Schema
-- Run this in phpMyAdmin or via:  mariadb -u root -p byabsayee_db < schema.sql
-- =============================================================================
-- HOW TO CREATE THE DATABASE FIRST:
--   1. Open phpMyAdmin (your existing one)
--   2. Click "New" in the left sidebar
--   3. Name it: byabsayee_db
--   4. Collation: utf8mb4_unicode_ci  (supports Bengali + emoji)
--   5. Click Create
--   6. Then click "SQL" tab and paste this entire file
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+06:00';   -- Bangladesh Standard Time (BST = UTC+6)
SET foreign_key_checks = 1;

-- =============================================================================
-- USERS & AUTH TABLES
-- =============================================================================

-- The main users table
CREATE TABLE IF NOT EXISTS `users` (
    `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`                VARCHAR(120) NOT NULL,
    `email`               VARCHAR(180) NOT NULL UNIQUE,
    `password_hash`       VARCHAR(255) NOT NULL,
    `avatar`              VARCHAR(255) NULL DEFAULT NULL,     -- path to profile photo
    `phone`               VARCHAR(20)  NULL DEFAULT NULL,
    `status`              ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',

    -- Email verification
    `verification_token`  VARCHAR(128) NULL DEFAULT NULL,
    `email_verified_at`   DATETIME     NULL DEFAULT NULL,

    -- Timestamps
    `last_login_at`       DATETIME     NULL DEFAULT NULL,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`          DATETIME     NULL DEFAULT NULL,   -- soft delete

    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Remember-me tokens (for "stay logged in")
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED NOT NULL,
    `token`       VARCHAR(128) NOT NULL UNIQUE,  -- stored as SHA256 hash
    `expires_at`  DATETIME NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Password reset tokens
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email`       VARCHAR(180) NOT NULL,
    `token`       VARCHAR(128) NOT NULL,  -- stored as SHA256 hash
    `expires_at`  DATETIME NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_email` (`email`),
    INDEX `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- BOOKS
-- Each user can create multiple books (personal or business)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `books` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED NOT NULL,
    `name`        VARCHAR(120) NOT NULL,
    `type`        ENUM('personal','business') NOT NULL DEFAULT 'personal',
    `currency`    VARCHAR(10)  NOT NULL DEFAULT 'BDT',
    `currency_symbol` VARCHAR(5) NOT NULL DEFAULT '৳',
    `timezone`    VARCHAR(60)  NOT NULL DEFAULT 'Asia/Dhaka',
    `color`       VARCHAR(7)   NOT NULL DEFAULT '#1a6b4a',  -- hex color for UI
    `logo`        VARCHAR(255) NULL DEFAULT NULL,
    `description` TEXT         NULL DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME     NULL DEFAULT NULL,

    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Business book details (extra info only business books need)
CREATE TABLE IF NOT EXISTS `book_business_details` (
    `book_id`          INT UNSIGNED PRIMARY KEY,
    `business_name`    VARCHAR(180) NULL,
    `trade_license`    VARCHAR(60)  NULL,
    `tin`              VARCHAR(60)  NULL,
    `bin`              VARCHAR(60)  NULL,   -- VAT registration
    `address`          TEXT         NULL,
    `phone`            VARCHAR(30)  NULL,
    `email`            VARCHAR(180) NULL,
    `website`          VARCHAR(255) NULL,
    `invoice_prefix`   VARCHAR(20)  NOT NULL DEFAULT 'INV',
    `invoice_counter`  INT UNSIGNED NOT NULL DEFAULT 1,
    `footer_note`      TEXT         NULL,   -- appears on every invoice

    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- CONTACTS (personal book feature)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `contacts` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`     INT UNSIGNED NOT NULL,
    `name`        VARCHAR(120) NOT NULL,
    `phone`       VARCHAR(30)  NULL,
    `email`       VARCHAR(180) NULL,
    `address`     TEXT         NULL,
    `notes`       TEXT         NULL,
    `photo`       VARCHAR(255) NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME     NULL DEFAULT NULL,

    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    INDEX `idx_book` (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- ENTRIES (personal book: income/expense records)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `entries` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`      INT UNSIGNED NOT NULL,
    `contact_id`   INT UNSIGNED NULL DEFAULT NULL,
    `type`         ENUM('in','out') NOT NULL,          -- income or expense
    `title`        VARCHAR(255) NOT NULL,
    `description`  TEXT         NULL,
    `amount`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `entry_date`   DATE         NOT NULL,
    `entry_time`   TIME         NULL DEFAULT NULL,
    `attachments`  JSON         NULL DEFAULT NULL,     -- array of file paths
    `created_by`   INT UNSIGNED NULL,                  -- user who added it
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   DATETIME     NULL DEFAULT NULL,

    FOREIGN KEY (`book_id`)    REFERENCES `books`(`id`)    ON DELETE CASCADE,
    FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`) ON DELETE SET NULL,
    INDEX `idx_book`       (`book_id`),
    INDEX `idx_type`       (`type`),
    INDEX `idx_entry_date` (`entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- CUSTOMERS (business book)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `customers` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`      INT UNSIGNED NOT NULL,
    `name`         VARCHAR(120) NOT NULL,
    `phone`        VARCHAR(30)  NULL,
    `email`        VARCHAR(180) NULL,
    `address`      TEXT         NULL,
    `trade_license` VARCHAR(60) NULL,
    `points`       INT UNSIGNED NOT NULL DEFAULT 0,
    `notes`        TEXT         NULL,
    `photo`        VARCHAR(255) NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`   DATETIME     NULL DEFAULT NULL,

    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    INDEX `idx_book`  (`book_id`),
    INDEX `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- SUPPLIERS (business book)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `suppliers` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`      INT UNSIGNED NOT NULL,
    `name`         VARCHAR(120) NOT NULL,
    `company`      VARCHAR(180) NULL,
    `phone`        VARCHAR(30)  NULL,
    `email`        VARCHAR(180) NULL,
    `address`      TEXT         NULL,
    `notes`        TEXT         NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`   DATETIME     NULL DEFAULT NULL,

    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    INDEX `idx_book` (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- PRODUCT CATEGORIES (business book, nested)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `categories` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`     INT UNSIGNED NOT NULL,
    `parent_id`   INT UNSIGNED NULL DEFAULT NULL,   -- NULL = top-level category
    `name`        VARCHAR(120) NOT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`book_id`)   REFERENCES `books`(`id`)      ON DELETE CASCADE,
    FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- PRODUCTS / STOCK ITEMS (business book)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `products` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`           INT UNSIGNED NOT NULL,
    `category_id`       INT UNSIGNED NULL DEFAULT NULL,
    `name`              VARCHAR(255) NOT NULL,
    `sku`               VARCHAR(60)  NULL,              -- your internal code
    `barcode`           VARCHAR(60)  NULL,
    `description`       TEXT         NULL,
    `unit`              VARCHAR(30)  NOT NULL DEFAULT 'pcs',  -- pcs, kg, ltr, etc.
    `buy_price`         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `sell_price`        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `stock_qty`         DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    `low_stock_alert`   DECIMAL(15,3) NULL DEFAULT 5.000,    -- alert when below this
    `image`             VARCHAR(255) NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        DATETIME     NULL DEFAULT NULL,

    FOREIGN KEY (`book_id`)     REFERENCES `books`(`id`)      ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_book` (`book_id`),
    INDEX `idx_sku`  (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- INVOICES (business book)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `invoices` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`       INT UNSIGNED NOT NULL,
    `type`          ENUM('sale','purchase','pos') NOT NULL DEFAULT 'sale',
    `invoice_no`    VARCHAR(30)  NOT NULL,
    `customer_id`   INT UNSIGNED NULL DEFAULT NULL,
    `supplier_id`   INT UNSIGNED NULL DEFAULT NULL,
    `date`          DATE         NOT NULL,
    `due_date`      DATE         NULL DEFAULT NULL,
    `subtotal`      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `discount`      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `tax`           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total`         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `paid`          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `status`        ENUM('draft','sent','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    `notes`         TEXT         NULL,
    `created_by`    INT UNSIGNED NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME     NULL DEFAULT NULL,

    FOREIGN KEY (`book_id`)     REFERENCES `books`(`id`)     ON DELETE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
    INDEX `idx_book`       (`book_id`),
    INDEX `idx_invoice_no` (`invoice_no`),
    INDEX `idx_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `invoice_items` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_id`   INT UNSIGNED NOT NULL,
    `product_id`   INT UNSIGNED NULL DEFAULT NULL,
    `description`  VARCHAR(255) NOT NULL,
    `qty`          DECIMAL(15,3) NOT NULL DEFAULT 1.000,
    `unit_price`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `discount_pct` DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `line_total`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,

    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- EMPLOYEES & ROLES (business book)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `roles` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`         INT UNSIGNED NOT NULL,
    `name`            VARCHAR(60)  NOT NULL,
    `permissions`     JSON         NOT NULL DEFAULT ('{}'),
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `employees` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`      INT UNSIGNED NOT NULL,
    `user_id`      INT UNSIGNED NULL DEFAULT NULL,  -- if they also have a login
    `role_id`      INT UNSIGNED NULL DEFAULT NULL,
    `name`         VARCHAR(120) NOT NULL,
    `phone`        VARCHAR(30)  NULL,
    `email`        VARCHAR(180) NULL,
    `department`   VARCHAR(80)  NULL,
    `join_date`    DATE         NULL,
    `salary`       DECIMAL(12,2) NULL,
    `salary_type`  ENUM('monthly','daily','hourly') NOT NULL DEFAULT 'monthly',
    `bank_info`    TEXT         NULL,       -- stored encrypted in future phase
    `photo`        VARCHAR(255) NULL,
    `nid_image`    VARCHAR(255) NULL,
    `status`       ENUM('active','inactive','terminated') NOT NULL DEFAULT 'active',
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`   DATETIME     NULL DEFAULT NULL,

    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- AUDIT LOG — tracks every important action
-- =============================================================================

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`      INT UNSIGNED NULL,
    `user_id`      INT UNSIGNED NULL,
    `action`       VARCHAR(60)  NOT NULL,   -- e.g. 'invoice.created', 'user.login'
    `table_name`   VARCHAR(60)  NULL,
    `record_id`    INT UNSIGNED NULL,
    `old_values`   JSON         NULL,
    `new_values`   JSON         NULL,
    `ip_address`   VARCHAR(45)  NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_book`   (`book_id`),
    INDEX `idx_user`   (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_date`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- SAMPLE DATA — creates a test user so you can log in immediately
-- Password is:  password123   (change after first login!)
-- =============================================================================

INSERT IGNORE INTO `users`
    (`name`, `email`, `password_hash`, `status`, `email_verified_at`)
VALUES (
    'Admin',
    'admin@byabsayee.local',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password123
    'active',
    NOW()
);

-- =============================================================================
-- EXTENDED TABLES (added as part of the migration fix)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `book_currencies` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`    INT UNSIGNED NOT NULL,
    `code`       VARCHAR(10)  NOT NULL DEFAULT 'BDT',
    `symbol`     VARCHAR(5)   NOT NULL DEFAULT '৳',
    `is_default` TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_method_options` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`    INT UNSIGNED NOT NULL,
    `type`       ENUM('delivery','payment') NOT NULL,
    `label`      VARCHAR(120) NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_entries` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`      INT UNSIGNED NOT NULL,
    `type`         ENUM('in','out') NOT NULL,
    `category`     VARCHAR(60)  NOT NULL,
    `amount`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `description`  VARCHAR(255) NULL,
    `source_table` VARCHAR(60)  NULL,
    `source_id`    INT UNSIGNED NULL,
    `date`         DATE NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT UNSIGNED NOT NULL,
    `amount`     DECIMAL(15,2) NOT NULL,
    `method`     VARCHAR(60)   NOT NULL DEFAULT 'cash',
    `date`       DATE NOT NULL,
    `note`       TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_attachments` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT UNSIGNED NOT NULL,
    `filename`   VARCHAR(255) NOT NULL,
    `path`       VARCHAR(500) NOT NULL,
    `size`       INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_batches` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id`    INT UNSIGNED NOT NULL,
    `book_id`       INT UNSIGNED NOT NULL,
    `barcode`       VARCHAR(60) NULL,
    `buy_price`     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `sell_price`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `initial_qty`   DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    `remaining_qty` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `funds` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`    INT UNSIGNED NOT NULL,
    `type`       ENUM('in','out') NOT NULL DEFAULT 'in',
    `title`      VARCHAR(255) NOT NULL,
    `amount`     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `fund_date`  DATE NOT NULL,
    `note`       TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`   INT UNSIGNED NOT NULL,
    `name`      VARCHAR(120) NOT NULL,
    `icon`      VARCHAR(60)  NOT NULL DEFAULT 'fa-tag',
    `is_active` TINYINT(1)   NOT NULL DEFAULT 1,
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expenses` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`      INT UNSIGNED NOT NULL,
    `category_id`  INT UNSIGNED NULL DEFAULT NULL,
    `title`        VARCHAR(255) NOT NULL,
    `amount`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `expense_date` DATE NOT NULL,
    `paid_to`      VARCHAR(120) NULL,
    `note`         TEXT NULL,
    `attachment`   VARCHAR(255) NULL,
    `created_by`   INT UNSIGNED NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`book_id`)     REFERENCES `books`(`id`)             ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dues` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`     INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NULL DEFAULT NULL,
    `invoice_id`  INT UNSIGNED NULL DEFAULT NULL,
    `title`       VARCHAR(255) NOT NULL,
    `amount`      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `due_date`    DATE NULL,
    `note`        TEXT NULL,
    `status`      ENUM('unpaid','partial','paid','cancelled') NOT NULL DEFAULT 'unpaid',
    `created_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`book_id`)     REFERENCES `books`(`id`)     ON DELETE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`invoice_id`)  REFERENCES `invoices`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `due_payments` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `due_id`         INT UNSIGNED NOT NULL,
    `book_id`        INT UNSIGNED NOT NULL,
    `amount`         DECIMAL(15,2) NOT NULL,
    `payment_method` VARCHAR(60) NOT NULL DEFAULT 'cash',
    `note`           TEXT NULL,
    `paid_by`        INT UNSIGNED NULL,
    `paid_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`due_id`) REFERENCES `dues`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `debts` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`     INT UNSIGNED NOT NULL,
    `supplier_id` INT UNSIGNED NULL DEFAULT NULL,
    `invoice_id`  INT UNSIGNED NULL DEFAULT NULL,
    `title`       VARCHAR(255) NOT NULL,
    `party`       VARCHAR(120) NULL,
    `amount`      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `due_date`    DATE NULL,
    `note`        TEXT NULL,
    `status`      ENUM('unpaid','partial','paid','cancelled') NOT NULL DEFAULT 'unpaid',
    `created_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`book_id`)     REFERENCES `books`(`id`)     ON DELETE CASCADE,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`invoice_id`)  REFERENCES `invoices`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `debt_payments` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `debt_id`        INT UNSIGNED NOT NULL,
    `book_id`        INT UNSIGNED NOT NULL,
    `amount`         DECIMAL(15,2) NOT NULL,
    `payment_method` VARCHAR(60) NOT NULL DEFAULT 'cash',
    `note`           TEXT NULL,
    `paid_by`        INT UNSIGNED NULL,
    `paid_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`debt_id`) REFERENCES `debts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupons` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`        INT UNSIGNED NOT NULL,
    `name`           VARCHAR(120) NOT NULL,
    `code`           VARCHAR(30)  NOT NULL,
    `discount_type`  ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
    `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `note`           TEXT NULL,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `expires_at`     DATETIME NULL,
    `created_by`     INT UNSIGNED NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uq_book_code` (`book_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extended columns for invoices
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `points_discount`  DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `discount`,
    ADD COLUMN IF NOT EXISTS `delivery_charge`  DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `points_discount`,
    ADD COLUMN IF NOT EXISTS `handling_charge`  DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `delivery_charge`,
    ADD COLUMN IF NOT EXISTS `delivery_type`    VARCHAR(30)   NULL DEFAULT 'own'    AFTER `handling_charge`,
    ADD COLUMN IF NOT EXISTS `rounding`         DECIMAL(10,4) NOT NULL DEFAULT 0    AFTER `delivery_type`,
    ADD COLUMN IF NOT EXISTS `note_customer`    TEXT NULL                           AFTER `notes`,
    ADD COLUMN IF NOT EXISTS `note_seller`      TEXT NULL                           AFTER `note_customer`,
    ADD COLUMN IF NOT EXISTS `delivery_method`  VARCHAR(120) NULL                   AFTER `note_seller`,
    ADD COLUMN IF NOT EXISTS `payment_method`   VARCHAR(120) NULL                   AFTER `delivery_method`,
    ADD COLUMN IF NOT EXISTS `theme_color`      VARCHAR(7)   NULL DEFAULT '#1a6b4a' AFTER `payment_method`,
    ADD COLUMN IF NOT EXISTS `currency_symbol`  VARCHAR(5)   NOT NULL DEFAULT '৳'   AFTER `theme_color`,
    ADD COLUMN IF NOT EXISTS `currency_code`    VARCHAR(10)  NOT NULL DEFAULT 'BDT' AFTER `currency_symbol`,
    ADD COLUMN IF NOT EXISTS `public_token`     VARCHAR(40)  NULL                   AFTER `currency_code`,
    ADD COLUMN IF NOT EXISTS `coupon_code`      VARCHAR(30)  NULL                   AFTER `public_token`,
    ADD COLUMN IF NOT EXISTS `coupon_discount`  DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `coupon_code`;

ALTER TABLE `coupons`
    ADD COLUMN IF NOT EXISTS `expires_at`  DATETIME NULL AFTER `is_active`,
    ADD COLUMN IF NOT EXISTS `updated_at`  DATETIME NULL AFTER `expires_at`;

ALTER TABLE `invoice_items`
    ADD COLUMN IF NOT EXISTS `variant` VARCHAR(120) NULL AFTER `description`;

ALTER TABLE `book_business_details`
    ADD COLUMN IF NOT EXISTS `inventory_method`         ENUM('FIFO','LIFO') NOT NULL DEFAULT 'FIFO' AFTER `invoice_counter`,
    ADD COLUMN IF NOT EXISTS `invoice_prefix_purchase`  VARCHAR(20) NOT NULL DEFAULT 'PUR'          AFTER `inventory_method`,
    ADD COLUMN IF NOT EXISTS `invoice_counter_purchase` INT UNSIGNED NOT NULL DEFAULT 1             AFTER `invoice_prefix_purchase`;

ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `product_code` VARCHAR(60) NULL AFTER `sku`;

-- ────────────────────────────────────────
-- Additional columns missing from originals
-- ────────────────────────────────────────
ALTER TABLE `books`
    ADD COLUMN IF NOT EXISTS `theme_color` VARCHAR(7)   NULL DEFAULT '#1a6b4a' AFTER `color`,
    ADD COLUMN IF NOT EXISTS `email`       VARCHAR(180) NULL                   AFTER `description`,
    ADD COLUMN IF NOT EXISTS `phone`       VARCHAR(30)  NULL                   AFTER `email`;

ALTER TABLE `book_business_details`
    ADD COLUMN IF NOT EXISTS `invoice_font`             VARCHAR(60)  NOT NULL DEFAULT 'DejaVu Sans' AFTER `footer_note`,
    ADD COLUMN IF NOT EXISTS `inventory_method`         ENUM('FIFO','LIFO') NOT NULL DEFAULT 'FIFO' AFTER `invoice_counter`,
    ADD COLUMN IF NOT EXISTS `invoice_prefix_purchase`  VARCHAR(20)  NOT NULL DEFAULT 'PUR'         AFTER `inventory_method`,
    ADD COLUMN IF NOT EXISTS `invoice_counter_purchase` INT UNSIGNED NOT NULL DEFAULT 1             AFTER `invoice_prefix_purchase`;

ALTER TABLE `book_currencies`
    ADD COLUMN IF NOT EXISTS `name` VARCHAR(80) NULL AFTER `symbol`;

-- returns / return_items
CREATE TABLE IF NOT EXISTS `returns` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`         INT UNSIGNED NOT NULL,
    `invoice_id`      INT UNSIGNED NULL DEFAULT NULL,
    `type`            ENUM('sales_return','purchase_return') NOT NULL,
    `return_no`       VARCHAR(40)  NOT NULL,
    `date`            DATE         NOT NULL,
    `customer_id`     INT UNSIGNED NULL DEFAULT NULL,
    `supplier_id`     INT UNSIGNED NULL DEFAULT NULL,
    `subtotal`        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `discount`        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `delivery_charge` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_refund`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `remarks`         TEXT NULL,
    `status`          VARCHAR(20) NOT NULL DEFAULT 'completed',
    `created_by`      INT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`      DATETIME NULL DEFAULT NULL,
    FOREIGN KEY (`book_id`)     REFERENCES `books`(`id`)     ON DELETE CASCADE,
    FOREIGN KEY (`invoice_id`)  REFERENCES `invoices`(`id`)  ON DELETE SET NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
    INDEX `idx_book` (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `return_items` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `return_id`   INT UNSIGNED NOT NULL,
    `product_id`  INT UNSIGNED NULL DEFAULT NULL,
    `description` VARCHAR(255) NOT NULL,
    `qty`         DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    `unit_price`  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `line_total`  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (`return_id`)  REFERENCES `returns`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- MISSING TABLES & COLUMNS — Added to fix runtime errors
-- =============================================================================

-- ── privilege_discount column missing from invoices ──────────────────────────
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `privilege_discount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `coupon_discount`;

-- ── Missing columns on employees ─────────────────────────────────────────────
ALTER TABLE `employees`
    ADD COLUMN IF NOT EXISTS `designation_id`   INT UNSIGNED NULL DEFAULT NULL AFTER `role_id`,
    ADD COLUMN IF NOT EXISTS `designation_name` VARCHAR(120) NULL AFTER `designation_id`,
    ADD COLUMN IF NOT EXISTS `invitation_id`    INT UNSIGNED NULL DEFAULT NULL AFTER `designation_name`,
    ADD COLUMN IF NOT EXISTS `address`          TEXT NULL AFTER `email`,
    ADD COLUMN IF NOT EXISTS `notes`            TEXT NULL AFTER `bank_info`;

-- ── Missing privilege_id on customers ────────────────────────────────────────
ALTER TABLE `customers`
    ADD COLUMN IF NOT EXISTS `privilege_id` INT UNSIGNED NULL DEFAULT NULL AFTER `points`;

-- ── Customer privileges tiers ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `customer_privileges` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`        INT UNSIGNED NOT NULL,
    `name`           VARCHAR(80)  NOT NULL,
    `discount_type`  ENUM('fixed','percent') NOT NULL DEFAULT 'percent',
    `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `description`    TEXT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    INDEX `idx_book` (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Customer → privilege junction (multi-privilege support) ───────────────────
CREATE TABLE IF NOT EXISTS `customer_privilege_assignments` (
    `customer_id`  INT UNSIGNED NOT NULL,
    `privilege_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`customer_id`, `privilege_id`),
    FOREIGN KEY (`customer_id`)  REFERENCES `customers`(`id`)          ON DELETE CASCADE,
    FOREIGN KEY (`privilege_id`) REFERENCES `customer_privileges`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Employee designations / roles ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `designations` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`     INT UNSIGNED NOT NULL,
    `name`        VARCHAR(80)  NOT NULL,
    `permissions` JSON         NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    INDEX `idx_book` (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Book members (employees with login access) ────────────────────────────────
CREATE TABLE IF NOT EXISTS `book_members` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`          INT UNSIGNED NOT NULL,
    `user_id`          INT UNSIGNED NOT NULL,
    `designation_id`   INT UNSIGNED NULL DEFAULT NULL,
    `designation_name` VARCHAR(120) NULL,
    `permissions`      JSON         NULL,
    `status`           ENUM('active','inactive','pending') NOT NULL DEFAULT 'active',
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_book_user` (`book_id`, `user_id`),
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Employee invitations ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `employee_invitations` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`          INT UNSIGNED NOT NULL,
    `invited_by`       INT UNSIGNED NULL,
    `email`            VARCHAR(180) NOT NULL,
    `user_id`          INT UNSIGNED NULL DEFAULT NULL,
    `designation_id`   INT UNSIGNED NULL DEFAULT NULL,
    `designation_name` VARCHAR(120) NULL,
    `permissions`      JSON         NULL,
    `token`            VARCHAR(80)  NOT NULL,
    `status`           ENUM('pending','accepted','expired','cancelled') NOT NULL DEFAULT 'pending',
    `expires_at`       DATETIME NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_token` (`token`),
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Employee salary payments ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `employee_salary_payments` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`        INT UNSIGNED NOT NULL,
    `employee_id`    INT UNSIGNED NOT NULL,
    `expense_id`     INT UNSIGNED NULL DEFAULT NULL,
    `amount`         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `period_label`   VARCHAR(60)  NULL,
    `period_from`    DATE         NULL,
    `period_to`      DATE         NULL,
    `payment_method` VARCHAR(80)  NULL,
    `note`           TEXT         NULL,
    `created_by`     INT UNSIGNED NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`book_id`)     REFERENCES `books`(`id`)     ON DELETE CASCADE,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
    INDEX `idx_book`     (`book_id`),
    INDEX `idx_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Notifications ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `book_id`    INT UNSIGNED NULL DEFAULT NULL,
    `type`       VARCHAR(40)  NOT NULL DEFAULT 'info',
    `title`      VARCHAR(255) NOT NULL,
    `body`       TEXT         NULL,
    `action_url` VARCHAR(400) NULL,
    `data`       JSON         NULL,
    `read_at`    DATETIME     NULL DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user`    (`user_id`),
    INDEX `idx_book`    (`book_id`),
    INDEX `idx_read_at` (`read_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Product variants ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_variants` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `label`      VARCHAR(60)  NOT NULL,
    `value`      VARCHAR(120) NOT NULL,
    `sku`        VARCHAR(60)  NULL,
    `price_adj`  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `stock_qty`  DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    INDEX `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Stock adjustments ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `type`       ENUM('add','remove','correction') NOT NULL DEFAULT 'add',
    `qty`        DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    `note`       TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    INDEX `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ACTIVITY LOG — comprehensive audit trail for every book action
-- =============================================================================

CREATE TABLE IF NOT EXISTS `activity_log` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`      INT UNSIGNED NULL,
    `user_id`      INT UNSIGNED NULL,
    `action`       VARCHAR(80)  NOT NULL,
    `subject_type` VARCHAR(60)  NULL,
    `subject_id`   INT UNSIGNED NULL,
    `description`  TEXT         NULL,
    `old_data`     JSON         NULL,
    `new_data`     JSON         NULL,
    `ip_address`   VARCHAR(45)  NULL,
    `user_agent`   VARCHAR(500) NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_book`    (`book_id`),
    INDEX `idx_user`    (`user_id`),
    INDEX `idx_action`  (`action`),
    INDEX `idx_date`    (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
