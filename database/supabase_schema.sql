-- ============================================================
--  Fashion Management System – Database Schema (PostgreSQL)
--  Fashion Studio Ghana
--  Target: Supabase / PostgreSQL
-- ============================================================

-- ─── EXTENSIONS ──────────────────────────────────────────────
-- Enable any necessary extensions
-- CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ─── CUSTOM TYPES ────────────────────────────────────────────
DROP TYPE IF EXISTS user_role CASCADE;
CREATE TYPE user_role AS ENUM ('customer', 'staff', 'admin');

DROP TYPE IF EXISTS order_status CASCADE;
CREATE TYPE order_status AS ENUM ('pending', 'approved', 'in-progress', 'completed', 'cancelled');

DROP TYPE IF EXISTS payment_method CASCADE;
CREATE TYPE payment_method AS ENUM ('cash', 'mobile_money', 'bank_transfer', 'other');

-- ─── USERS (Authentication) ────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(120)  NOT NULL,
    email       VARCHAR(180)  NOT NULL UNIQUE,
    phone       VARCHAR(20)   NOT NULL,
    password    VARCHAR(255)  NOT NULL,
    role        user_role     NOT NULL DEFAULT 'customer',
    is_active   BOOLEAN       NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── CUSTOMERS (Extended Profiles) ────────────────────────
CREATE TABLE IF NOT EXISTS customers (
    id          SERIAL PRIMARY KEY,
    user_id     INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    address     TEXT,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── MEASUREMENTS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS measurements (
    id             SERIAL PRIMARY KEY,
    customer_id    INT NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    bust           DECIMAL(5,2),
    waist          DECIMAL(5,2),
    hips           DECIMAL(5,2),
    height         DECIMAL(5,2),
    shoulder       DECIMAL(5,2),
    inseam         DECIMAL(5,2),
    sleeve_length  DECIMAL(5,2),
    neck           DECIMAL(5,2),
    notes          TEXT,
    recorded_by    INT REFERENCES users(id) ON DELETE SET NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── STYLES ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS styles (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    description TEXT,
    base_price  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image_path  VARCHAR(255) DEFAULT 'assets/images/styles/default.jpg',
    is_active   BOOLEAN  NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── FABRICS / INVENTORY ───────────────────────────────────
CREATE TABLE IF NOT EXISTS fabrics (
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    fabric_type     VARCHAR(80),
    color           VARCHAR(60),
    quantity_yards  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cost_per_yard   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    supplier        VARCHAR(120),
    reorder_level   DECIMAL(10,2) NOT NULL DEFAULT 5.00,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── ORDERS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id              SERIAL PRIMARY KEY,
    customer_id     INT NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    style_id        INT REFERENCES styles(id) ON DELETE SET NULL,
    fabric_id       INT REFERENCES fabrics(id) ON DELETE SET NULL,
    quantity        INT NOT NULL DEFAULT 1,
    status          order_status NOT NULL DEFAULT 'pending',
    notes           TEXT,
    self_bust       DECIMAL(5,2),
    self_waist      DECIMAL(5,2),
    self_hips       DECIMAL(5,2),
    self_height     DECIMAL(5,2),
    assigned_to     INT REFERENCES users(id) ON DELETE SET NULL,
    total_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── SALES ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sales (
    id              SERIAL PRIMARY KEY,
    order_id        INT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    amount          DECIMAL(10,2) NOT NULL,
    payment_method  payment_method NOT NULL DEFAULT 'cash',
    recorded_by     INT REFERENCES users(id) ON DELETE SET NULL,
    sale_date       DATE NOT NULL,
    notes           TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── INVENTORY LOG ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inventory_log (
    id          SERIAL PRIMARY KEY,
    fabric_id   INT NOT NULL REFERENCES fabrics(id) ON DELETE CASCADE,
    change_qty  DECIMAL(10,2) NOT NULL,
    reason      VARCHAR(255),
    recorded_by INT REFERENCES users(id) ON DELETE SET NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── AUDIT LOG ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_log (
    id          SERIAL PRIMARY KEY,
    user_id     INT REFERENCES users(id) ON DELETE SET NULL,
    action      VARCHAR(120) NOT NULL,
    details     TEXT,
    ip_address  VARCHAR(45),
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── NOTIFICATIONS ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id          SERIAL PRIMARY KEY,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message     TEXT NOT NULL,
    is_read     BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── TRIGGERS FOR UPDATED_AT ────────────────────────────────
-- Function to update updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Triggers
CREATE TRIGGER update_users_modtime BEFORE UPDATE ON users FOR EACH ROW EXECUTE PROCEDURE update_updated_at_column();
CREATE TRIGGER update_measurements_modtime BEFORE UPDATE ON measurements FOR EACH ROW EXECUTE PROCEDURE update_updated_at_column();
CREATE TRIGGER update_fabrics_modtime BEFORE UPDATE ON fabrics FOR EACH ROW EXECUTE PROCEDURE update_updated_at_column();
CREATE TRIGGER update_orders_modtime BEFORE UPDATE ON orders FOR EACH ROW EXECUTE PROCEDURE update_updated_at_column();

-- ─── SEED DATA ──────────────────────────────────────────────

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
