-- Seed Dummies
USE procurementDB;

-- Companies
INSERT INTO companies (name, address, city, province, postal_code, phone, email, is_default) VALUES
('PT. Sinar Mas Property', 'Jl. Thamrin No. 1', 'Jakarta', 'DKI Jakarta', '10110', '021-1234567', 'contact@sinarmas.com', 0),
('PT. Agung Podomoro', 'Jl. Sudirman No. 2', 'Jakarta', 'DKI Jakarta', '10120', '021-7654321', 'contact@agungpodomoro.com', 0),
('PT. Wika Beton', 'Jl. Gatot Subroto', 'Jakarta', 'DKI Jakarta', '10130', '021-5555555', 'sales@wikabeton.id', 0),
('PT. Adhi Karya', 'Jl. HR Rasuna Said', 'Jakarta', 'DKI Jakarta', '10140', '021-4444444', 'info@adhi.co.id', 0),
('PT. PP Presisi', 'Jl. MT Haryono', 'Jakarta', 'DKI Jakarta', '10150', '021-3333333', 'hello@pppresisi.co.id', 0);

-- Categories
INSERT INTO categories (name, prefix, description) VALUES
('Besi Beton', 'BSB', 'Berbagai macam besi beton'),
('Semen', 'SMN', 'Berbagai jenis semen'),
('Baja Ringan', 'BJR', 'Rangka baja ringan'),
('Cat & Pelapis', 'CAT', 'Cat tembok, kayu, pelapis anti bocor'),
('Ubin & Keramik', 'KRM', 'Keramik lantai dan dinding');

-- Vendors
INSERT INTO vendors (company_name, abbreviation, pic_name, phone, address) VALUES
('Toko Bangunan Makmur', 'MAK', 'Budi Santoso', '081234567890', 'Jl. Raya Bogor No. 10'),
('PT. Baja Utama Indah', 'BUI', 'Andi Wijaya', '081298765432', 'Kawasan Industri Pulogadung'),
('CV. Semen Jaya', 'SMJ', 'Cipto', '08155556666', 'Jl. Daan Mogot KM 10'),
('TB. Aneka Keramik', 'AKR', 'Susi', '087788889999', 'Panglima Polim No. 5'),
('PT. Sumber Listrik', 'SBL', 'Hendra', '089912341234', 'Glodok Makmur Blok A');

-- Customers
INSERT INTO customers (company_name, abbreviation, pic_name, phone, address) VALUES
('PT. Developer Rumah Bersama', 'DRB', 'Alex', '08111112222', 'Jl. BSD Boulevard'),
('Kementerian PUPR', 'PPR', 'Basuki', '021-1111111', 'Jl. Pattimura 20'),
('PT. Jaya Konstruksi', 'JAK', 'Rudi', '08222223333', 'Jl. Bintaro Raya'),
('Dinas Tata Kota Batam', 'DTK', 'Ahmad', '0778-123456', 'Batam Center'),
('PT. Summarecon', 'SMR', 'Dina', '08333334444', 'Kelapa Gading');

-- Projects (Assuming customer 1-5 exists from the previous insert)
INSERT INTO projects (name, customer_id, location, status) VALUES
('Pembangunan Perumahan BSD', 1, 'Kawasan BSD City', 'active'),
('Renovasi Gedung Kementerian', 2, 'Gedung KemenPUPR Jakarta', 'planning'),
('Flyover Bintaro', 3, 'Jl. Bintaro Sektor 7', 'active'),
('Penataan Pedestrian Batam', 4, 'Batam Center Boulevard', 'active'),
('Cluster Baru Summarecon', 5, 'Summarecon Serpong', 'planning');

-- Items
INSERT INTO items (category_id, item_code, description, type_specification, uom, minimum_stock, warehouse_location, remark, stock_type) VALUES
((SELECT id FROM categories WHERE prefix='BSB' LIMIT 1), 'BSB-0001', 'Besi Beton Ulir 10mm', 'SNI', 'Btg', 100, 'Rak A1', 'Besi standart konstruksi', 'stock'),
((SELECT id FROM categories WHERE prefix='SMN' LIMIT 1), 'SMN-0001', 'Semen Tiga Roda 50kg', 'PCC', 'ZAK', 50, 'Gudang Timur', 'Jangan kena lembab', 'stock'),
((SELECT id FROM categories WHERE prefix='BJR' LIMIT 1), 'BJR-0001', 'Baja Ringan Canal C75', 'Tebal 0.75mm', 'Btg', 200, 'Area Luar', '', 'stock'),
((SELECT id FROM categories WHERE prefix='CAT' LIMIT 1), 'CAT-0001', 'Cat Dulux Weathershield 20L', 'Putih', 'Pail', 10, 'Rak B2', 'Exterior', 'stock'),
((SELECT id FROM categories WHERE prefix='KRM' LIMIT 1), 'KRM-0001', 'Keramik Roman 60x60', 'Glossy Putih', 'Dus', 30, 'Rak C1', 'Lantai ruang tamu', 'stock');
