-- =============================================
-- E-Procurement Database Schema
-- Database: procurementDB
-- =============================================


-- =============================================
-- 1. USERS & AUTHENTICATION
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin','finance','gudang','project_manager') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    gender ENUM('Laki-laki','Perempuan') DEFAULT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    photo VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- 2. COMPANIES (Multi-Company Header)
-- =============================================
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    province VARCHAR(100),
    postal_code VARCHAR(10),
    phone VARCHAR(30),
    email VARCHAR(100),
    logo VARCHAR(255),
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- 3. CATEGORIES
-- =============================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    prefix VARCHAR(5) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- 4. ITEMS
-- =============================================
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(20) UNIQUE NOT NULL,
    category_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    type_specification VARCHAR(100),
    uom VARCHAR(20) NOT NULL,
    minimum_stock DECIMAL(12,2) DEFAULT 0,
    warehouse_location VARCHAR(100),
    remark TEXT,
    stock_type ENUM('stock','direct') DEFAULT 'stock',
    current_stock DECIMAL(12,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- =============================================
-- 5. VENDORS
-- =============================================
CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    abbreviation VARCHAR(15) NOT NULL,
    address TEXT,
    pic_name VARCHAR(100),
    phone VARCHAR(30),
    email VARCHAR(100),
    bank_name VARCHAR(50),
    bank_account VARCHAR(50),
    bank_holder VARCHAR(100),
    payment_terms VARCHAR(50),
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- 6. CUSTOMERS
-- =============================================
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    abbreviation VARCHAR(20) NOT NULL,
    address TEXT,
    pic_name VARCHAR(100),
    phone VARCHAR(30),
    email VARCHAR(100),
    bank_name VARCHAR(50),
    bank_account VARCHAR(50),
    bank_holder VARCHAR(100),
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- 7. PROJECTS
-- =============================================
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    abbreviation VARCHAR(5) NOT NULL,
    location TEXT,
    start_date DATE,
    end_date DATE,
    budget DECIMAL(15,2) DEFAULT 0,
    project_manager_id INT,
    customer_id INT,
    customer_name VARCHAR(150),
    customer_contact VARCHAR(30),
    status ENUM('planning','active','completed','cancelled') DEFAULT 'planning',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_manager_id) REFERENCES users(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB;

-- =============================================
-- 8. MATERIAL REQUESTS
-- =============================================
CREATE TABLE IF NOT EXISTS material_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mr_number VARCHAR(20) UNIQUE NOT NULL,
    project_id INT NOT NULL,
    requested_by INT NOT NULL,
    request_date DATE NOT NULL,
    location TEXT,
    status ENUM('draft','pending','approved','rejected','completed') DEFAULT 'draft',
    approved_by INT,
    approved_at DATETIME,
    reject_reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS material_request_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mr_id INT NOT NULL,
    item_id INT NOT NULL,
    description VARCHAR(255),
    type_specification VARCHAR(100),
    qty DECIMAL(12,2) NOT NULL,
    uom VARCHAR(20),
    remark TEXT,
    qty_ordered DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mr_id) REFERENCES material_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB;

-- =============================================
-- 9. PURCHASE ORDERS
-- =============================================
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) UNIQUE NOT NULL,
    vendor_id INT NOT NULL,
    company_id INT NOT NULL,
    po_date DATE NOT NULL,
    delivery_address TEXT,
    delivery_contact VARCHAR(30),
    delivery_attn VARCHAR(100),
    delivery_date DATE,
    requested_by VARCHAR(100),
    terms VARCHAR(50),
    subtotal DECIMAL(15,2) DEFAULT 0,
    shipping DECIMAL(15,2) DEFAULT 0,
    tax DECIMAL(15,2) DEFAULT 0,
    discount DECIMAL(15,2) DEFAULT 0,
    other_cost DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    additional_notes TEXT,
    status ENUM('draft','pending','approved','rejected','partially_received','completed','cancelled') DEFAULT 'draft',
    created_by INT,
    approved_by INT,
    approved_at DATETIME,
    reject_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    item_id INT,
    mr_item_id INT,
    item_name VARCHAR(255) NOT NULL,
    qty DECIMAL(12,2) NOT NULL,
    uom VARCHAR(20),
    unit_price DECIMAL(15,2) DEFAULT 0,
    discount_item DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    qty_received DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id),
    FOREIGN KEY (mr_item_id) REFERENCES material_request_items(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS po_mr_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    mr_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (mr_id) REFERENCES material_requests(id)
) ENGINE=InnoDB;

-- =============================================
-- 10. GOODS RECEIVING
-- =============================================
CREATE TABLE IF NOT EXISTS goods_receivings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    receive_date DATE NOT NULL,
    surat_jalan_no VARCHAR(50),
    received_by INT NOT NULL,
    received_at ENUM('warehouse','project') DEFAULT 'warehouse',
    project_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (received_by) REFERENCES users(id),
    FOREIGN KEY (project_id) REFERENCES projects(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS goods_receiving_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receiving_id INT NOT NULL,
    po_item_id INT NOT NULL,
    qty_received DECIMAL(12,2) NOT NULL,
    qty_rejected DECIMAL(12,2) DEFAULT 0,
    reject_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (receiving_id) REFERENCES goods_receivings(id) ON DELETE CASCADE,
    FOREIGN KEY (po_item_id) REFERENCES purchase_order_items(id)
) ENGINE=InnoDB;

-- =============================================
-- 11. STOCK & WAREHOUSE
-- =============================================
CREATE TABLE IF NOT EXISTS stock_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    transaction_type ENUM('in','out','transfer_out','transfer_in','adjustment') NOT NULL,
    qty DECIMAL(12,2) NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT,
    project_id INT,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id),
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS warehouse_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_number VARCHAR(20) UNIQUE NOT NULL,
    transfer_date DATE NOT NULL,
    to_project_id INT NOT NULL,
    transferred_by INT NOT NULL,
    notes TEXT,
    status ENUM('draft','completed','cancelled') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (to_project_id) REFERENCES projects(id),
    FOREIGN KEY (transferred_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS warehouse_transfer_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    item_id INT NOT NULL,
    qty DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transfer_id) REFERENCES warehouse_transfers(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB;

-- =============================================
-- 12. VENDOR PAYMENTS
-- =============================================
CREATE TABLE IF NOT EXISTS vendor_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(50),
    payment_term VARCHAR(50),
    bank_name VARCHAR(50),
    bank_account VARCHAR(50),
    reference_no VARCHAR(50),
    notes TEXT,
    paid_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (paid_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =============================================
-- 13. QUOTATIONS
-- =============================================
CREATE TABLE IF NOT EXISTS quotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quotation_no VARCHAR(20) UNIQUE NOT NULL,
    company_id INT NOT NULL,
    customer_id INT NOT NULL,
    project_id INT,
    quotation_date DATE NOT NULL,
    valid_from DATE,
    valid_until DATE,
    comments TEXT,
    subtotal DECIMAL(15,2) DEFAULT 0,
    shipping DECIMAL(15,2) DEFAULT 0,
    tax DECIMAL(15,2) DEFAULT 0,
    discount DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    status ENUM('draft','pending','approved','rejected','invoiced') DEFAULT 'draft',
    created_by INT,
    approved_by INT,
    approved_at DATETIME,
    reject_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quotation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quotation_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    type_specification VARCHAR(100),
    qty DECIMAL(12,2) NOT NULL,
    uom VARCHAR(20),
    material_unit_price DECIMAL(15,2) DEFAULT 0,
    material_total DECIMAL(15,2) DEFAULT 0,
    manpower_unit_price DECIMAL(15,2) DEFAULT 0,
    manpower_total DECIMAL(15,2) DEFAULT 0,
    amount DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- 14. INVOICES
-- =============================================
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(20) UNIQUE NOT NULL,
    quotation_id INT NOT NULL,
    company_id INT NOT NULL,
    customer_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    termin_no INT DEFAULT 1,
    termin_description VARCHAR(100),
    subtotal DECIMAL(15,2) DEFAULT 0,
    shipping DECIMAL(15,2) DEFAULT 0,
    tax DECIMAL(15,2) DEFAULT 0,
    discount DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    term_and_conditions TEXT,
    status ENUM('draft','pending','approved','rejected','sent','partial_paid','paid') DEFAULT 'draft',
    created_by INT,
    approved_by INT,
    approved_at DATETIME,
    reject_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quotation_id) REFERENCES quotations(id),
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    type_specification VARCHAR(100),
    qty DECIMAL(12,2) NOT NULL,
    uom VARCHAR(20),
    material_unit_price DECIMAL(15,2) DEFAULT 0,
    material_total DECIMAL(15,2) DEFAULT 0,
    manpower_unit_price DECIMAL(15,2) DEFAULT 0,
    manpower_total DECIMAL(15,2) DEFAULT 0,
    amount DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- 15. CUSTOMER PAYMENTS
-- =============================================
CREATE TABLE IF NOT EXISTS customer_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(50),
    reference_no VARCHAR(50),
    notes TEXT,
    received_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (received_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =============================================
-- SEED DATA
-- =============================================

-- Default Super Admin (password: admin123)
INSERT INTO users (username, password, role, full_name, email, is_active) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'Super Administrator', 'admin@megakarya.com', 1);

-- Default Company
INSERT INTO companies (name, address, city, province, postal_code, phone, email, is_default) VALUES
('PT. Mega Karya Modern', 'Ruko Golden City, Bengkong Laut, Blok I no 10 RT 3 RW 4', 'Batam', 'Kepulauan Riau', '29458', '+62 812-7417-1386', 'megakaryamodern@gmail.com', 1);

-- Default Categories
INSERT INTO categories (name, prefix, description) VALUES
('Construction Material', 'CMB', 'Material konstruksi bangunan'),
('Interior Material', 'ITM', 'Material interior'),
('Tools & Equipment', 'TLS', 'Alat-alat dan peralatan'),
('Electrical', 'ELC', 'Material kelistrikan'),
('Plumbing', 'PLB', 'Material pipa dan sanitasi');
