-- =====================================================================
-- StyleNest database schema  (MySQL 5.7+ / MariaDB 10.3+)
-- Import this file first, then database/seed.sql
-- =====================================================================
CREATE DATABASE IF NOT EXISTS stylenest_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stylenest_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS login_attempts, contact_messages, admin_activity_logs, newsletter_subscribers,
  order_status_history, payments, order_items, orders, addresses, cart_items, carts,
  product_variants, product_images, products, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

-- Customer and staff accounts -------------------------------------------
CREATE TABLE users (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(120) NOT NULL,
  email          VARCHAR(190) NOT NULL,
  phone          VARCHAR(20)  NULL,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('customer','manager','admin') NOT NULL DEFAULT 'customer',
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at  DATETIME     NULL,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB;

-- Collections ---------------------------------------------------------------
CREATE TABLE categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  slug        VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  image       VARCHAR(255) NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB;

-- Products ----------------------------------------------------------------
CREATE TABLE products (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id   INT UNSIGNED NOT NULL,
  name          VARCHAR(160) NOT NULL,
  slug          VARCHAR(180) NOT NULL,
  description   TEXT NULL,
  details       TEXT NULL,
  price         DECIMAL(10,2) NOT NULL,
  compare_price DECIMAL(10,2) NULL,
  status        ENUM('active','unavailable','draft') NOT NULL DEFAULT 'active',
  is_featured   TINYINT(1) NOT NULL DEFAULT 0,
  label         VARCHAR(40) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_products_slug (slug),
  KEY idx_products_category (category_id),
  KEY idx_products_status (status, is_featured),
  KEY idx_products_price (price),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

CREATE TABLE product_images (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  image_path  VARCHAR(255) NOT NULL,
  alt_text    VARCHAR(190) NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  KEY idx_images_product (product_id, sort_order),
  CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Size / colour combinations, each with its own stock -----------------------
CREATE TABLE product_variants (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  sku        VARCHAR(60) NOT NULL,
  size       VARCHAR(20) NOT NULL,
  color      VARCHAR(40) NOT NULL,
  color_hex  CHAR(7) NOT NULL DEFAULT '#24231F',
  stock      INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_variants_sku (sku),
  UNIQUE KEY uq_variants_combo (product_id, size, color),
  CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Persistent carts for signed-in customers -----------------------------------
CREATE TABLE carts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_carts_user (user_id),
  CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cart_items (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id    INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NOT NULL,
  quantity   INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_cart_variant (cart_id, variant_id),
  CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Saved delivery addresses ---------------------------------------------------
CREATE TABLE addresses (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  label       VARCHAR(50) NOT NULL DEFAULT 'Home',
  full_name   VARCHAR(120) NOT NULL,
  phone       VARCHAR(20) NOT NULL,
  address     VARCHAR(255) NOT NULL,
  town        VARCHAR(100) NOT NULL,
  county      VARCHAR(60) NOT NULL,
  is_default  TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_addresses_user (user_id),
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Orders ---------------------------------------------------------------------
CREATE TABLE orders (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number     VARCHAR(30) NOT NULL,
  user_id          INT UNSIGNED NULL,
  customer_name    VARCHAR(120) NOT NULL,
  email            VARCHAR(190) NULL,
  phone            VARCHAR(20)  NOT NULL,
  delivery_address VARCHAR(255) NOT NULL,
  town             VARCHAR(100) NOT NULL,
  county           VARCHAR(60)  NOT NULL,
  notes            VARCHAR(500) NULL,
  subtotal         DECIMAL(10,2) NOT NULL,
  delivery_fee     DECIMAL(10,2) NOT NULL DEFAULT 0,
  total            DECIMAL(10,2) NOT NULL,
  payment_method   ENUM('mpesa','cod') NOT NULL,
  payment_status   ENUM('unpaid','pending','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
  status           ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_orders_number (order_number),
  KEY idx_orders_user (user_id),
  KEY idx_orders_status (status, payment_status),
  KEY idx_orders_created (created_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Price, size and colour are copied at purchase time so history stays accurate
CREATE TABLE order_items (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      INT UNSIGNED NOT NULL,
  product_id    INT UNSIGNED NULL,
  variant_id    INT UNSIGNED NULL,
  product_name  VARCHAR(160) NOT NULL,
  sku           VARCHAR(60) NULL,
  size          VARCHAR(20) NOT NULL,
  color         VARCHAR(40) NOT NULL,
  unit_price    DECIMAL(10,2) NOT NULL,
  quantity      INT UNSIGNED NOT NULL,
  line_total    DECIMAL(10,2) NOT NULL,
  KEY idx_items_order (order_id),
  KEY idx_items_product (product_id),
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id   INT UNSIGNED NOT NULL,
  status     VARCHAR(30) NOT NULL,
  note       VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_history_order (order_id),
  CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Payments -------------------------------------------------------------------
CREATE TABLE payments (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id             INT UNSIGNED NOT NULL,
  method               ENUM('mpesa','cod') NOT NULL,
  amount               DECIMAL(10,2) NOT NULL,
  phone                VARCHAR(20) NULL,
  status               ENUM('initiated','pending','success','failed','cancelled') NOT NULL DEFAULT 'initiated',
  merchant_request_id  VARCHAR(80) NULL,
  checkout_request_id  VARCHAR(80) NULL,
  mpesa_receipt        VARCHAR(40) NULL,
  result_code          INT NULL,
  result_desc          VARCHAR(255) NULL,
  raw_callback         MEDIUMTEXT NULL,
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_payments_order (order_id),
  KEY idx_payments_checkout (checkout_request_id),
  KEY idx_payments_status (status),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Newsletter (only stores people who ticked consent) --------------------------
CREATE TABLE newsletter_subscribers (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL,
  consent       TINYINT(1) NOT NULL DEFAULT 1,
  subscribed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_newsletter_email (email)
) ENGINE=InnoDB;

CREATE TABLE contact_messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(190) NOT NULL,
  subject    VARCHAR(160) NOT NULL,
  message    TEXT NOT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admin_activity_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(80) NOT NULL,
  details    VARCHAR(500) NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_logs_created (created_at),
  CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Used to slow down password guessing ---------------------------------------
CREATE TABLE login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_address   VARCHAR(45) NOT NULL,
  email        VARCHAR(190) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attempts_lookup (ip_address, email, attempted_at)
) ENGINE=InnoDB;
