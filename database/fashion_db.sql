-- ============================================================
--  Fashion Management System – Database Schema
--  Fashion Studio Ghana
--  Import via phpMyAdmin at http://localhost/phpmyadmin
-- ============================================================

CREATE DATABASE IF NOT EXISTS fashion_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fashion_db;

-- ─── USERS (Authentication) ────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120)  NOT NULL,
    email       VARCHAR(180)  NOT NULL UNIQUE,
    phone       VARCHAR(20)   NOT NULL,
    password    VARCHAR(255)  NOT NULL,
    role        ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── CUSTOMERS (Extended Profiles) ────────────────────────
CREATE TABLE IF NOT EXISTS customers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL UNIQUE,
    address     TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── MEASUREMENTS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS measurements (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT UNSIGNED NOT NULL,
    bust           DECIMAL(5,2),
    waist          DECIMAL(5,2),
    hips           DECIMAL(5,2),
    height         DECIMAL(5,2),
    shoulder       DECIMAL(5,2),
    inseam         DECIMAL(5,2),
    sleeve_length  DECIMAL(5,2),
    neck           DECIMAL(5,2),
    notes          TEXT,
    recorded_by    INT UNSIGNED,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── STYLES ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS styles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    description TEXT,
    base_price  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image_path  VARCHAR(255) DEFAULT 'assets/images/styles/default.jpg',
    is_active   TINYINT(1)  NOT NULL DEFAULT 1,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── FABRICS / INVENTORY ───────────────────────────────────
CREATE TABLE IF NOT EXISTS fabrics (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    fabric_type     VARCHAR(80),
    color           VARCHAR(60),
    quantity_yards  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cost_per_yard   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    supplier        VARCHAR(120),
    reorder_level   DECIMAL(10,2) NOT NULL DEFAULT 5.00,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── ORDERS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT UNSIGNED NOT NULL,
    style_id        INT UNSIGNED,
    fabric_id       INT UNSIGNED,
    quantity        INT UNSIGNED NOT NULL DEFAULT 1,
    status          ENUM('pending','approved','in-progress','completed','cancelled') NOT NULL DEFAULT 'pending',
    notes           TEXT,
    self_bust       DECIMAL(5,2),
    self_waist      DECIMAL(5,2),
    self_hips       DECIMAL(5,2),
    self_height     DECIMAL(5,2),
    assigned_to     INT UNSIGNED,
    total_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (style_id)   REFERENCES styles(id) ON DELETE SET NULL,
    FOREIGN KEY (fabric_id)  REFERENCES fabrics(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── SALES ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sales (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    payment_method  ENUM('cash','mobile_money','bank_transfer','other') NOT NULL DEFAULT 'cash',
    recorded_by     INT UNSIGNED,
    sale_date       DATE NOT NULL,
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)    REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── INVENTORY LOG ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inventory_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fabric_id   INT UNSIGNED NOT NULL,
    change_qty  DECIMAL(10,2) NOT NULL,
    reason      VARCHAR(255),
    recorded_by INT UNSIGNED,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fabric_id)   REFERENCES fabrics(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── AUDIT LOG ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED,
    action      VARCHAR(120) NOT NULL,
    details     TEXT,
    ip_address  VARCHAR(45),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── NOTIFICATIONS ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    message     TEXT NOT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  SEED DATA
-- ============================================================

-- Admin: password = "Admin@1234"
INSERT INTO users (name, email, phone, password, role) VALUES
('Admin User',   'admin@fashionstudio.gh',  '+233200000001', '$2y$12$PGqjt5s9jQo5PF0X4mV2qOGVVD29vFWfMh/yAiMF/dpFVaIx5CUuC', 'admin'),
('Staff One',    'staff@fashionstudio.gh',  '+233200000002', '$2y$12$PGqjt5s9jQo5PF0X4mV2qOGVVD29vFWfMh/yAiMF/dpFVaIx5CUuC', 'staff'),
('Ama Owusu',    'ama@example.com',         '+233244123456', '$2y$12$PGqjt5s9jQo5PF0X4mV2qOGVVD29vFWfMh/yAiMF/dpFVaIx5CUuC', 'customer'),
('Akua Mensah',  'akua@example.com',        '+233208765432', '$2y$12$PGqjt5s9jQo5PF0X4mV2qOGVVD29vFWfMh/yAiMF/dpFVaIx5CUuC', 'customer');

INSERT INTO customers (user_id, address) VALUES
(3, 'Kumasi, Ashanti Region, Ghana'),
(4, 'Accra, Greater Accra Region, Ghana');

INSERT INTO styles (name, description, base_price, image_path) VALUES
('Kente Gown',       'Elegant Kente-inspired formal gown for special occasions', 450.00, 'assets/images/styles/kente_gown.jpg'),
('Ankara Jumpsuit',  'Modern Ankara print jumpsuit, fully customizable fit', 280.00, 'assets/images/styles/ankara_jumpsuit.jpg'),
('Corporate Skirt Suit', 'Professional tailored skirt suit for the modern woman', 380.00, 'assets/images/styles/skirt_suit.jpg'),
('Casual Sundress',  'Light and breezy everyday dress with African print', 180.00, 'assets/images/styles/sundress.jpg'),
('Bridal Gown',      'Custom bridal gown with intricate hand-sewn details', 1200.00,'assets/images/styles/bridal.jpg'),
('Agbada Set',       'Traditional Agbada ensemble for men, custom embroidery', 550.00, 'assets/images/styles/agbada.jpg');

INSERT INTO fabrics (name, fabric_type, color, quantity_yards, cost_per_yard, supplier, reorder_level) VALUES
('Ghana Kente',          'Kente',    'Multi-color', 25.00, 35.00, 'Kente Weavers Bonwire', 5.00),
('Ankara Print A',       'Ankara',   'Blue/Yellow', 40.00, 12.00, 'Kantamanto Market',    8.00),
('Ankara Print B',       'Ankara',   'Red/Green',   30.00, 12.00, 'Kantamanto Market',    8.00),
('Plain White Cotton',   'Cotton',   'White',       60.00,  6.00, 'Makola Market',        10.00),
('Pink Satin',           'Satin',    'Rose Pink',   20.00, 18.00, 'Accra Fabrics Ltd',    4.00),
('Black Linen',          'Linen',    'Jet Black',   35.00, 10.00, 'Makola Market',        6.00),
('Gold Brocade',         'Brocade',  'Gold',        15.00, 25.00, 'Premium Fabrics GH',   3.00),
('Silk Chiffon',         'Chiffon',  'Ivory',       10.00, 22.00, 'Premium Fabrics GH',   2.00);

INSERT INTO measurements (customer_id, bust, waist, hips, height, shoulder, inseam, sleeve_length, neck, notes, recorded_by) VALUES
(1, 38.00, 30.00, 40.00, 64.00, 14.50, 28.00, 24.00, 14.00, 'Standard fit preferred', 2),
(2, 36.00, 28.00, 38.00, 62.00, 13.50, 26.00, 23.00, 13.50, 'Slim fit', 2);

INSERT INTO orders (customer_id, style_id, fabric_id, quantity, status, notes, total_amount, assigned_to) VALUES
(1, 1, 1, 1, 'completed',    'Please use bright kente, formal occasion',  450.00, 2),
(1, 2, 2, 2, 'in-progress',  'Prefer wide-leg trousers style',            560.00, 2),
(2, 3, 6, 1, 'approved',     'Corporate event, need by end of month',     380.00, 2),
(2, 4, 2, 1, 'pending',      'Casual weekend wear',                       180.00, NULL);

INSERT INTO sales (order_id, amount, payment_method, recorded_by, sale_date) VALUES
(1, 450.00, 'cash', 2, '2026-03-01');
