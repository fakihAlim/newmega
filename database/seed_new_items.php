<?php
/**
 * Seeder to replace Categories and Items master data
 */
require_once __DIR__ . '/../config.php';

// Check if run via CLI or if authorized
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    require_once __DIR__ . '/../includes/auth.php';
    if (!hasRole('super_admin')) {
        die('Access Denied. Only super_admin can run this script.');
    }
}

try {
    // Disable Foreign Key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // Modify categories prefix length limit to support multi-level prefixes (e.g. CN-SEM)
    $pdo->exec("ALTER TABLE categories MODIFY prefix VARCHAR(10) NOT NULL;");
    
    echo "Truncating existing categories and items...\n";
    $pdo->exec("TRUNCATE TABLE items;");
    $pdo->exec("DELETE FROM categories;");
    $pdo->exec("ALTER TABLE categories AUTO_INCREMENT = 1;");
    $pdo->exec("ALTER TABLE items AUTO_INCREMENT = 1;");
    
    // Insert new categories
    $categories = [
        ['name' => 'Semen & Mortar', 'prefix' => 'CN-SEM', 'description' => 'Kategori Konstruksi & Bangunan - Semen & Mortar'],
        ['name' => 'Besi & Baja Struktur', 'prefix' => 'CN-BSI', 'description' => 'Kategori Konstruksi & Bangunan - Besi & Baja Struktur'],
        ['name' => 'Bata & Agregat Kasar', 'prefix' => 'CN-BAG', 'description' => 'Kategori Konstruksi & Bangunan - Bata & Agregat Kasar'],
        ['name' => 'Pipa PVC & PPR', 'prefix' => 'PP-PIP', 'description' => 'Kategori Piping / Perpipaan - Pipa PVC & PPR'],
        ['name' => 'Fitting / Sambungan Pipa', 'prefix' => 'PP-FIT', 'description' => 'Kategori Piping / Perpipaan - Fitting / Sambungan Pipa'],
        ['name' => 'Valve & Aksesoris Perpipaan', 'prefix' => 'PP-VLV', 'description' => 'Kategori Piping / Perpipaan - Valve & Aksesoris Perpipaan'],
        ['name' => 'Kabel Listrik', 'prefix' => 'EL-CBL', 'description' => 'Kategori Electrical / Kelistrikan - Kabel Listrik'],
        ['name' => 'Saklar, Stopkontak & Aksesoris', 'prefix' => 'EL-SWT', 'description' => 'Kategori Electrical / Kelistrikan - Saklar, Stopkontak & Aksesoris'],
        ['name' => 'Proteksi Panel & Lampu', 'prefix' => 'EL-PAN', 'description' => 'Kategori Electrical / Kelistrikan - Proteksi Panel & Lampu']
    ];
    
    $insCat = $pdo->prepare("INSERT INTO categories (name, prefix, description) VALUES (?, ?, ?)");
    $categoryMap = []; // to map prefix to auto-incremented ID
    
    foreach ($categories as $cat) {
        $insCat->execute([$cat['name'], $cat['prefix'], $cat['description']]);
        $categoryId = $pdo->lastInsertId();
        $categoryMap[$cat['prefix']] = $categoryId;
        echo "[OK] Created category: {$cat['name']} ({$cat['prefix']})\n";
    }
    
    // Insert new items
    $items = [
        // Semen & Mortar (CN-SEM)
        [
            'prefix' => 'CN-SEM', 'code' => 'CN-SEM-001',
            'desc' => 'Semen Portland Standard',
            'spec' => 'PCC (Portland Composite Cement), Tiga Roda / Padang',
            'uom' => 'Zak', 'remark' => 'Kantong 40 Kg'
        ],
        [
            'prefix' => 'CN-SEM', 'code' => 'CN-SEM-002',
            'desc' => 'Semen Portland Standard',
            'spec' => 'OPC (Ordinary Portland Cement) Type I, Untuk Cor Struktur',
            'uom' => 'Zak', 'remark' => 'Kantong 50 Kg'
        ],
        [
            'prefix' => 'CN-SEM', 'code' => 'CN-SEM-003',
            'desc' => 'Semen Instant / Mortar',
            'spec' => 'Mortar Perekat Bata Ringan (Thin Bed), MU-301 / Setara',
            'uom' => 'Zak', 'remark' => 'Kantong 40 Kg'
        ],
        [
            'prefix' => 'CN-SEM', 'code' => 'CN-SEM-004',
            'desc' => 'Semen Instant / Mortar',
            'spec' => 'Mortar Plesteran Dinding, MU-101 / Setara',
            'uom' => 'Zak', 'remark' => 'Kantong 40 Kg'
        ],
        [
            'prefix' => 'CN-SEM', 'code' => 'CN-SEM-005',
            'desc' => 'Semen Instant / Mortar',
            'spec' => 'Pengisi Acian Dinding (Skim Coat)',
            'uom' => 'Zak', 'remark' => 'Kantong 40 Kg'
        ],

        // Besi & Baja Struktur (CN-BSI)
        [
            'prefix' => 'CN-BSI', 'code' => 'CN-BSI-001',
            'desc' => 'Besi Beton Polos',
            'spec' => 'Round Bar P8, Diameter 8mm, Panjang 12 Meter',
            'uom' => 'Btg', 'remark' => 'SNI'
        ],
        [
            'prefix' => 'CN-BSI', 'code' => 'CN-BSI-002',
            'desc' => 'Besi Beton Polos',
            'spec' => 'Round Bar P10, Diameter 10mm, Panjang 12 Meter',
            'uom' => 'Btg', 'remark' => 'SNI'
        ],
        [
            'prefix' => 'CN-BSI', 'code' => 'CN-BSI-003',
            'desc' => 'Besi Beton Ulir',
            'spec' => 'Deformed Bar D13, Diameter 13mm, Panjang 12 Meter',
            'uom' => 'Btg', 'remark' => 'SNI'
        ],
        [
            'prefix' => 'CN-BSI', 'code' => 'CN-BSI-004',
            'desc' => 'Besi Beton Ulir',
            'spec' => 'Deformed Bar D16, Diameter 16mm, Panjang 12 Meter',
            'uom' => 'Btg', 'remark' => 'SNI'
        ],
        [
            'prefix' => 'CN-BSI', 'code' => 'CN-BSI-005',
            'desc' => 'Kawat Bendrat',
            'spec' => 'Kawat Ikat Besi Beton, BWG 21',
            'uom' => 'Roll', 'remark' => 'Per Gulung (25 Kg)'
        ],
        [
            'prefix' => 'CN-BSI', 'code' => 'CN-BSI-006',
            'desc' => 'Wiremesh',
            'spec' => 'Besi Wiremesh M8, Ukuran Lembaran 2.1 Meter x 5.4 Meter',
            'uom' => 'Lbr', 'remark' => 'SNI'
        ],

        // Bata & Agregat Kasar (CN-BAG)
        [
            'prefix' => 'CN-BAG', 'code' => 'CN-BAG-001',
            'desc' => 'Bata Ringan (Hebel)',
            'spec' => 'Ukuran 7.5 cm x 20 cm x 60 cm',
            'uom' => 'M3', 'remark' => 'Per Kubik (M3)'
        ],
        [
            'prefix' => 'CN-BAG', 'code' => 'CN-BAG-002',
            'desc' => 'Bata Ringan (Hebel)',
            'spec' => 'Ukuran 10 cm x 20 cm x 60 cm',
            'uom' => 'M3', 'remark' => 'Per Kubik (M3)'
        ],
        [
            'prefix' => 'CN-BAG', 'code' => 'CN-BAG-003',
            'desc' => 'Bata Merah',
            'spec' => 'Bata Merah Pres Oven, Standard Bangunan',
            'uom' => 'Pcs', 'remark' => 'Per Seribu Pcs'
        ],

        // Pipa PVC & PPR (PP-PIP)
        [
            'prefix' => 'PP-PIP', 'code' => 'PP-PIP-001',
            'desc' => 'Pipa PVC Kelas AW',
            'spec' => 'Diameter 1/2 Inch, Aplikasi Air Bersih Bertekanan',
            'uom' => 'Btg', 'remark' => 'Panjang 4 Meter'
        ],
        [
            'prefix' => 'PP-PIP', 'code' => 'PP-PIP-002',
            'desc' => 'Pipa PVC Kelas AW',
            'spec' => 'Diameter 3/4 Inch, Aplikasi Air Bersih Bertekanan',
            'uom' => 'Btg', 'remark' => 'Panjang 4 Meter'
        ],
        [
            'prefix' => 'PP-PIP', 'code' => 'PP-PIP-003',
            'desc' => 'Pipa PVC Kelas AW',
            'spec' => 'Diameter 1 Inch, Aplikasi Air Bersih Bertekanan',
            'uom' => 'Btg', 'remark' => 'Panjang 4 Meter'
        ],
        [
            'prefix' => 'PP-PIP', 'code' => 'PP-PIP-004',
            'desc' => 'Pipa PVC Kelas D',
            'spec' => 'Diameter 3 Inch, Aplikasi Air Buangan / Limbah',
            'uom' => 'Btg', 'remark' => 'Panjang 4 Meter'
        ],
        [
            'prefix' => 'PP-PIP', 'code' => 'PP-PIP-005',
            'desc' => 'Pipa PVC Kelas D',
            'spec' => 'Diameter 4 Inch, Aplikasi Air Buangan / Air Kotor',
            'uom' => 'Btg', 'remark' => 'Panjang 4 Meter'
        ],
        [
            'prefix' => 'PP-PIP', 'code' => 'PP-PIP-006',
            'desc' => 'Pipa PPR PN-10',
            'spec' => 'Diameter 20 mm (1/2"), Aplikasi Air Dingin, Hijau',
            'uom' => 'Btg', 'remark' => 'Panjang 4 Meter'
        ],
        [
            'prefix' => 'PP-PIP', 'code' => 'PP-PIP-007',
            'desc' => 'Pipa PPR PN-20',
            'spec' => 'Diameter 20 mm (1/2"), Aplikasi Air Panas Bertekanan',
            'uom' => 'Btg', 'remark' => 'Panjang 4 Meter'
        ],

        // Fitting / Sambungan Pipa (PP-FIT)
        [
            'prefix' => 'PP-FIT', 'code' => 'PP-FIT-001',
            'desc' => 'Elbow PVC AW 90°',
            'spec' => 'Diameter 1/2 Inch, Sambungan L / Siku',
            'uom' => 'Pcs', 'remark' => ''
        ],
        [
            'prefix' => 'PP-FIT', 'code' => 'PP-FIT-002',
            'desc' => 'Elbow PVC AW 90°',
            'spec' => 'Diameter 3/4 Inch, Sambungan L / Siku',
            'uom' => 'Pcs', 'remark' => ''
        ],
        [
            'prefix' => 'PP-FIT', 'code' => 'PP-FIT-003',
            'desc' => 'Tee PVC AW',
            'spec' => 'Diameter 1/2 Inch, Sambungan T / Tiga Arah',
            'uom' => 'Pcs', 'remark' => ''
        ],
        [
            'prefix' => 'PP-FIT', 'code' => 'PP-FIT-004',
            'desc' => 'Socket PVC AW',
            'spec' => 'Diameter 1/2 Inch, Sambungan Lurus Pembesar/Penyambung',
            'uom' => 'Pcs', 'remark' => ''
        ],
        [
            'prefix' => 'PP-FIT', 'code' => 'PP-FIT-005',
            'desc' => 'Clean Out PVC D',
            'spec' => 'Diameter 4 Inch, Penutup Lubang Kontrol Pipa Limbah',
            'uom' => 'Pcs', 'remark' => ''
        ],

        // Valve & Aksesoris Perpipaan (PP-VLV)
        [
            'prefix' => 'PP-VLV', 'code' => 'PP-VLV-001',
            'desc' => 'Ball Valve PVC',
            'spec' => 'Diameter 1/2 Inch, Stop Kran Bahan PVC Handle Putar',
            'uom' => 'Pcs', 'remark' => ''
        ],
        [
            'prefix' => 'PP-VLV', 'code' => 'PP-VLV-002',
            'desc' => 'Ball Valve Kuningan',
            'spec' => 'Diameter 1/2 Inch, Kitz / Setara, Koneksi Ulir',
            'uom' => 'Pcs', 'remark' => ''
        ],

        // Kabel Listrik (EL-CBL)
        [
            'prefix' => 'EL-CBL', 'code' => 'EL-CBL-001',
            'desc' => 'Kabel NYA',
            'spec' => 'Ukuran 1 x 1.5 mm², Kawat Tunggal Lapis Tunggal, Supreme / Eterna',
            'uom' => 'Roll', 'remark' => 'Rol 100M'
        ],
        [
            'prefix' => 'EL-CBL', 'code' => 'EL-CBL-002',
            'desc' => 'Kabel NYA',
            'spec' => 'Ukuran 1 x 2.5 mm², Kawat Tunggal Lapis Tunggal, Supreme / Eterna',
            'uom' => 'Roll', 'remark' => 'Rol 100M'
        ],
        [
            'prefix' => 'EL-CBL', 'code' => 'EL-CBL-003',
            'desc' => 'Kabel NYM',
            'spec' => 'Ukuran 2 x 1.5 mm², Kawat Bungkus Putih (Isi 2)',
            'uom' => 'Roll', 'remark' => 'Rol 50 Meter'
        ],
        [
            'prefix' => 'EL-CBL', 'code' => 'EL-CBL-004',
            'desc' => 'Kabel NYM',
            'spec' => 'Ukuran 3 x 2.5 mm², Kawat Bungkus Putih (Isi 3 + Ground)',
            'uom' => 'Roll', 'remark' => 'Rol 50 Meter'
        ],
        [
            'prefix' => 'EL-CBL', 'code' => 'EL-CBL-005',
            'desc' => 'Kabel NYY',
            'spec' => 'Ukuran 3 x 2.5 mm², Kabel Tanah Bungkus Hitam',
            'uom' => 'Roll', 'remark' => 'Outdoor/Direct Burial'
        ],

        // Saklar, Stopkontak & Aksesoris (EL-SWT)
        [
            'prefix' => 'EL-SWT', 'code' => 'EL-SWT-001',
            'desc' => 'Saklar Tunggal',
            'spec' => 'Tipe Inbow / Tanam Dinding, Schneider / Panasonic',
            'uom' => 'Pcs', 'remark' => 'Putih'
        ],
        [
            'prefix' => 'EL-SWT', 'code' => 'EL-SWT-002',
            'desc' => 'Saklar Ganda / Seri',
            'spec' => 'Tipe Inbow / Tanam Dinding, Schneider / Panasonic',
            'uom' => 'Pcs', 'remark' => 'Putih'
        ],
        [
            'prefix' => 'EL-SWT', 'code' => 'EL-SWT-003',
            'desc' => 'Stopkontak Arde',
            'spec' => 'Tipe Inbow (Tanam), 1 Pin + Grounding Metal, 16A 250V',
            'uom' => 'Pcs', 'remark' => ''
        ],
        [
            'prefix' => 'EL-SWT', 'code' => 'EL-SWT-004',
            'desc' => 'Inbow Dus',
            'spec' => 'Kotak Tanam Dinding untuk Saklar/Stopkontak, Bahan Plastik Hitam',
            'uom' => 'Pcs', 'remark' => ''
        ],
        [
            'prefix' => 'EL-SWT', 'code' => 'EL-SWT-005',
            'desc' => 'T-Dus Plastik',
            'spec' => 'Kotak Percabangan Kabel, 3 atau 4 Arah',
            'uom' => 'Pcs', 'remark' => 'Diameter 2 Inch'
        ],

        // Proteksi Panel & Lampu (EL-PAN)
        [
            'prefix' => 'EL-PAN', 'code' => 'EL-PAN-001',
            'desc' => 'MCB 1 Pole (1 Fasa)',
            'spec' => 'Kapasitas 6 Ampere, Schneider / ABB',
            'uom' => 'Pcs', 'remark' => 'Proteksi 1300 Watt'
        ],
        [
            'prefix' => 'EL-PAN', 'code' => 'EL-PAN-002',
            'desc' => 'MCB 1 Pole (1 Fasa)',
            'spec' => 'Kapasitas 10 Ampere, Schneider / ABB',
            'uom' => 'Pcs', 'remark' => 'Proteksi 2200 Watt'
        ]
    ];
    
    $insItem = $pdo->prepare("
        INSERT INTO items (category_id, item_code, description, type_specification, uom, minimum_stock, warehouse_location, remark, stock_type, current_stock, is_active) 
        VALUES (?, ?, ?, ?, ?, 0, '', ?, 'stock', 0, 1)
    ");
    
    foreach ($items as $item) {
        $catId = $categoryMap[$item['prefix']] ?? null;
        if ($catId) {
            $insItem->execute([
                $catId,
                $item['code'],
                $item['desc'],
                $item['spec'],
                $item['uom'],
                $item['remark']
            ]);
            echo "[OK] Created item: {$item['code']} - {$item['desc']}\n";
        } else {
            echo "[ERROR] Missing category for prefix: {$item['prefix']}\n";
        }
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "\nSeeder run completed successfully!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
