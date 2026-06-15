<?php
/**
 * Migration Script - Landing Page & CMS Feature
 * Run this script to create landing page tables and register permissions.
 */

require_once __DIR__ . '/../config.php';

try {
    echo "Starting landing page migration...\n";

    // 1. Create landing_banners table
    $sqlBanners = "
    CREATE TABLE IF NOT EXISTS landing_banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        subtitle TEXT NULL,
        image_url VARCHAR(255) NOT NULL,
        button_text VARCHAR(100) DEFAULT NULL,
        button_url VARCHAR(255) DEFAULT NULL,
        order_num INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlBanners);
    echo "Table 'landing_banners' created or verified.\n";

    // 2. Create landing_services table
    $sqlServices = "
    CREATE TABLE IF NOT EXISTS landing_services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        icon VARCHAR(100) DEFAULT 'fa-hard-hat',
        order_num INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlServices);
    echo "Table 'landing_services' created or verified.\n";

    // 3. Create landing_portfolios table
    $sqlPortfolios = "
    CREATE TABLE IF NOT EXISTS landing_portfolios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        category VARCHAR(100) DEFAULT 'Residensial',
        client VARCHAR(150) DEFAULT NULL,
        project_date DATE DEFAULT NULL,
        image_url VARCHAR(255) NOT NULL,
        order_num INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlPortfolios);
    echo "Table 'landing_portfolios' created or verified.\n";

    // 4. Create landing_tips table
    $sqlTips = "
    CREATE TABLE IF NOT EXISTS landing_tips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        excerpt TEXT NULL,
        author VARCHAR(100) DEFAULT NULL,
        image_url VARCHAR(255) NOT NULL,
        published_date DATE DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlTips);
    echo "Table 'landing_tips' created or verified.\n";

    // 5. Seed Initial Data if empty
    // Banners
    $countBanners = $pdo->query("SELECT COUNT(*) FROM landing_banners")->fetchColumn();
    if ($countBanners == 0) {
        $stmt = $pdo->prepare("INSERT INTO landing_banners (title, subtitle, image_url, button_text, button_url, order_num) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'Membangun Masa Depan Presisi & Kuat',
            'PT. Mega Karya Modern menghadirkan konstruksi berkualitas tinggi dengan standar keandalan tinggi dan manajemen profesional.',
            'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=1200&q=80',
            'Kalkulator Konstruksi',
            'kalkulator.php',
            1
        ]);
        $stmt->execute([
            'Komitmen pada Mutu Struktur & Desain',
            'Dari perancangan hingga serah terima, setiap detail diproses dengan keahlian teknik sipil terbaik.',
            'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=1200&q=80',
            'Lihat Portofolio',
            '#portfolio',
            2
        ]);
        echo "Seeded landing_banners.\n";
    }

    // Services
    $countServices = $pdo->query("SELECT COUNT(*) FROM landing_services")->fetchColumn();
    if ($countServices == 0) {
        $stmt = $pdo->prepare("INSERT INTO landing_services (title, description, icon, order_num) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            'Pembangunan Residensial',
            'Konstruksi rumah tinggal mewah, villa, dan perumahan dengan material kokoh dan finishing premium.',
            'fa-home',
            1
        ]);
        $stmt->execute([
            'Konstruksi Komersial',
            'Ruko, gedung kantor, dan fasilitas bisnis yang dioptimalkan untuk efisiensi ruang dan daya tahan struktural.',
            'fa-building',
            2
        ]);
        $stmt->execute([
            'Renovasi Struktural',
            'Perbaikan dan peningkatan kekuatan bangunan lama dengan analisis ketahanan gempa dan pembebanan modern.',
            'fa-tools',
            3
        ]);
        $stmt->execute([
            'Desain Arsitektur & Perencanaan',
            'Gambar kerja detail, perhitungan kekuatan struktur (RAB), dan perencanaan blueprint proyek konstruksi.',
            'fa-drafting-line',
            4
        ]);
        echo "Seeded landing_services.\n";
    }

    // Portfolios
    $countPortfolios = $pdo->query("SELECT COUNT(*) FROM landing_portfolios")->fetchColumn();
    if ($countPortfolios == 0) {
        $stmt = $pdo->prepare("INSERT INTO landing_portfolios (title, description, category, client, project_date, image_url, order_num) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'Villa Mewah Golden City',
            'Pembangunan villa 3 lantai dengan struktur beton bertulang K-300 dan finishing marmer.',
            'Residensial',
            'Bapak Budi Santoso',
            '2025-08-10',
            'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=800&q=80',
            1
        ]);
        $stmt->execute([
            'Ruko Mega Komersial Batam',
            'Kompleks rumah toko modern dengan konsep minimalis industrial di kawasan Bengkong Laut.',
            'Komersial',
            'PT. Mega Property Group',
            '2026-02-15',
            'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=800&q=80',
            2
        ]);
        $stmt->execute([
            'Kantor Pusat MKM',
            'Gedung perkantoran ramah lingkungan dengan kombinasi facade kaca dan struktur baja ringan.',
            'Komersial',
            'PT. Mega Karya Modern',
            '2025-12-01',
            'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=800&q=80',
            3
        ]);
        echo "Seeded landing_portfolios.\n";
    }

    // Tips & Tricks
    $countTips = $pdo->query("SELECT COUNT(*) FROM landing_tips")->fetchColumn();
    if ($countTips == 0) {
        $stmt = $pdo->prepare("INSERT INTO landing_tips (title, content, excerpt, author, image_url, published_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'Cara Menghitung Kebutuhan Semen untuk Cor Beton Kolom',
            'Menghitung semen secara tepat sangat krusial untuk mutu beton. Untuk beton standar K-225 (1:2:3), Anda membutuhkan sekitar 326 kg semen per meter kubik beton. Pastikan perbandingan volume air dan semen terkontrol agar beton tidak retak saat mengering.',
            'Panduan lengkap menghitung kebutuhan semen, pasir, dan batu pecah untuk pengerjaan beton struktural agar kokoh dan tahan lama.',
            'Ir. Hermawan',
            'https://images.unsplash.com/photo-1581094288338-2314dddb7ecc?auto=format&fit=crop&w=800&q=80',
            '2026-05-12'
        ]);
        $stmt->execute([
            'Mengapa Bata Ringan (Hebel) Lebih Cepat Dibanding Bata Merah?',
            'Bata ringan memiliki dimensi yang jauh lebih besar (biasanya 60x20x7.5 cm atau 60x20x10 cm) dibandingkan bata merah standar. Karena ukurannya yang besar namun bobotnya ringan, proses pemasangan dinding menjadi 3x lebih cepat. Selain itu, penggunaan mortar instan juga meminimalisir semen tercecer.',
            'Kelebihan bata ringan hebel dalam mempercepat durasi pengerjaan dinding rumah serta efisiensi anggaran semen pasir.',
            'Suryono (Site Manager)',
            'https://images.unsplash.com/photo-1590069261209-f8e9b8642343?auto=format&fit=crop&w=800&q=80',
            '2026-06-01'
        ]);
        echo "Seeded landing_tips.\n";
    }

    // 6. Register Permissions in role_permissions table
    // Fetch all roles from roles table
    $stmtRoles = $pdo->query("SELECT id, role_key FROM roles");
    $roles = $stmtRoles->fetchAll();

    $stmtCheckPerm = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND module_key = 'cms_landing'");
    $stmtInsPerm = $pdo->prepare("INSERT INTO role_permissions (role_id, module_key, can_view, can_create, can_edit, can_delete) VALUES (?, 'cms_landing', 1, 1, 1, 1)");

    foreach ($roles as $role) {
        // We will seed view/create/edit/delete permission for 'super_admin'
        // For other roles, they can configure it in UI.
        if ($role['role_key'] === 'super_admin') {
            $stmtCheckPerm->execute([$role['id']]);
            if ($stmtCheckPerm->fetchColumn() == 0) {
                $stmtInsPerm->execute([$role['id']]);
                echo "Registered 'cms_landing' permissions for role '{$role['role_key']}'.\n";
            } else {
                echo "Permissions for 'cms_landing' already exist for role '{$role['role_key']}'.\n";
            }
        }
    }

    echo "Landing page migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
