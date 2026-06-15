<?php
/**
 * Application Landing Page
 * PT. Mega Karya Modern
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// Fetch Active Banners
try {
    $stmt = $pdo->prepare("SELECT * FROM landing_banners WHERE is_active = 1 ORDER BY order_num ASC");
    $stmt->execute();
    $banners = $stmt->fetchAll();
} catch (Exception $e) {
    $banners = [];
}

// Fetch Active Services
try {
    $stmt = $pdo->prepare("SELECT * FROM landing_services WHERE is_active = 1 ORDER BY order_num ASC");
    $stmt->execute();
    $services = $stmt->fetchAll();
} catch (Exception $e) {
    $services = [];
}

// Fetch Active Portfolios
try {
    $stmt = $pdo->prepare("SELECT * FROM landing_portfolios WHERE is_active = 1 ORDER BY order_num ASC");
    $stmt->execute();
    $portfolios = $stmt->fetchAll();
} catch (Exception $e) {
    $portfolios = [];
}

// Fetch Active Tips & Tricks
try {
    $stmt = $pdo->prepare("SELECT * FROM landing_tips WHERE is_active = 1 ORDER BY published_date DESC LIMIT 3");
    $stmt->execute();
    $tips = $stmt->fetchAll();
} catch (Exception $e) {
    $tips = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="PT. Mega Karya Modern - Konstruksi Sipil, Pembangunan Rumah, Ruko & Gedung Komersial Berkelas dengan Integritas dan Presisi.">
    <title>PT. Mega Karya Modern | Jasa Konstruksi Premium</title>

    <!-- Google Fonts: Montserrat & Work Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Font Awesome 5 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Bootstrap 4 for grid & components compatibility with the dashboard -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
        /* CSS RESET & DESIGN SYSTEM VARIABLES */
        :root {
            --primary: #091426;
            --primary-container: #1e293b;
            --secondary: #914d00;
            --accent: #fc9430; /* Construction Orange */
            --accent-hover: #e57d1b;
            --background: #f7f9fb;
            --surface: #ffffff;
            --surface-variant: #e0e3e5;
            --on-surface: #191c1e;
            --on-surface-variant: #45474c;
            --border-color: #c5c6cd;
            --border-radius: 4px; /* Soft 4px as per design.md */
        }

        body {
            font-family: 'Work Sans', sans-serif;
            background-color: var(--background);
            color: var(--on-surface);
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        /* Typography Override */
        h1, h2, h3, h4, h5, h6, .font-display {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
        }

        .label-caps {
            font-family: 'Work Sans', sans-serif;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--accent);
        }

        /* Navigation Bar Styling */
        .navbar-custom {
            background-color: var(--primary) !important;
            padding: 15px 80px;
            border-bottom: 2px solid var(--accent);
            transition: all 0.3s;
        }

        @media (max-width: 1023px) {
            .navbar-custom {
                padding: 15px 20px;
            }
        }

        .navbar-custom .navbar-brand {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 22px;
            color: #ffffff !important;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-custom .nav-link {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 14px;
            color: #d8e3fb !important;
            text-transform: uppercase;
            padding: 8px 16px !important;
            transition: all 0.2s;
        }

        .navbar-custom .nav-link:hover,
        .navbar-custom .nav-item.active .nav-link {
            color: var(--accent) !important;
        }

        .btn-cta-nav {
            background-color: var(--accent) ;
            color: #ffffff !important;
            border-radius: var(--border-radius);
            font-weight: 700;
            transition: all 0.3s !important;
        }

        .btn-cta-nav:hover {
            background-color: var(--accent-hover) !important;
            transform: translateY(-1px);
        }

        /* Section Layouts */
        .section-padding {
            padding: 120px 80px;
        }

        @media (max-width: 767px) {
            .section-padding {
                padding: 60px 16px;
            }
        }

        .section-bg-dark {
            background-color: var(--primary);
            color: #ffffff;
        }

        .section-title-container {
            margin-bottom: 60px;
        }

        .section-title {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -0.01em;
            position: relative;
            margin-bottom: 15px;
        }

        .section-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background-color: var(--accent);
            margin-top: 12px;
        }

        /* Hero Carousel Slider */
        .hero-carousel .carousel-item {
            height: 650px;
            background-color: var(--primary);
            position: relative;
        }

        @media (max-width: 767px) {
            .hero-carousel .carousel-item {
                height: 480px;
            }
        }

        .hero-carousel .carousel-img-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(rgba(9, 20, 38, 0.7), rgba(9, 20, 38, 0.85));
            z-index: 1;
        }

        .hero-carousel .carousel-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-carousel .carousel-caption-custom {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 2;
            text-align: center;
            width: 80%;
            max-width: 900px;
            color: #ffffff;
        }

        .hero-carousel h1 {
            font-size: 56px;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 20px;
            letter-spacing: -0.02em;
        }

        @media (max-width: 767px) {
            .hero-carousel h1 {
                font-size: 32px;
            }
        }

        .hero-carousel p {
            font-size: 18px;
            color: #cbd5e1;
            margin-bottom: 30px;
            font-family: 'Work Sans', sans-serif;
        }

        @media (max-width: 767px) {
            .hero-carousel p {
                font-size: 14px;
            }
        }

        .btn-premium {
            background-color: var(--accent);
            color: #ffffff;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 16px;
            padding: 14px 30px;
            border-radius: var(--border-radius);
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: all 0.3s;
            display: inline-block;
            text-decoration: none;
        }

        .btn-premium:hover {
            background-color: var(--accent-hover);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(252, 148, 48, 0.4);
        }

        .btn-outline-premium {
            background-color: transparent;
            color: #ffffff;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 16px;
            padding: 12px 28px;
            border-radius: var(--border-radius);
            border: 2px solid #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: all 0.3s;
            display: inline-block;
            text-decoration: none;
        }

        .btn-outline-premium:hover {
            background-color: #ffffff;
            color: var(--primary);
            text-decoration: none;
            transform: translateY(-2px);
        }

        /* Services Grid Styling */
        .service-card {
            background: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 40px 30px;
            height: 100%;
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            position: relative;
        }

        /* Hard border hover shadow effect as specified in design.md */
        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: 4px 4px 0px 0px var(--primary);
        }

        .service-icon-box {
            width: 60px;
            height: 60px;
            background-color: rgba(252, 148, 48, 0.1);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            font-size: 24px;
            color: var(--accent);
            border: 1px solid rgba(252, 148, 48, 0.2);
        }

        .service-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .service-card p {
            font-size: 15px;
            color: var(--on-surface-variant);
            line-height: 1.6;
        }

        /* Portfolio Grid Styling */
        .portfolio-filter-btn {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 1px solid var(--border-color);
            background: var(--surface);
            color: var(--primary);
            padding: 8px 20px;
            border-radius: var(--border-radius);
            margin: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .portfolio-filter-btn.active, .portfolio-filter-btn:hover {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        .portfolio-item {
            margin-bottom: 30px;
        }

        .portfolio-card {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            background: var(--surface);
            position: relative;
            transition: all 0.3s;
            height: 100%;
        }

        .portfolio-card:hover {
            transform: translateY(-4px);
            box-shadow: 4px 4px 0px 0px var(--primary);
        }

        .portfolio-img-container {
            position: relative;
            overflow: hidden;
            height: 240px;
        }

        .portfolio-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }

        .portfolio-card:hover .portfolio-img {
            transform: scale(1.05);
        }

        .portfolio-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background-color: var(--accent);
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 4px 10px;
            border-radius: var(--border-radius);
            z-index: 10;
        }

        .portfolio-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(9, 20, 38, 0.9);
            opacity: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            text-align: center;
            transition: opacity 0.3s;
            z-index: 20;
        }

        .portfolio-card:hover .portfolio-overlay {
            opacity: 1;
        }

        .portfolio-overlay h4 {
            color: #ffffff;
            font-size: 18px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .portfolio-overlay p {
            color: #cbd5e1;
            font-size: 13px;
            margin-bottom: 15px;
        }

        .portfolio-card-info {
            padding: 20px;
        }

        .portfolio-card-info h4 {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .portfolio-card-info p {
            font-size: 14px;
            color: var(--on-surface-variant);
            margin-bottom: 0;
        }

        /* Tips & Tricks Card Styling */
        .tips-card {
            background: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            height: 100%;
            transition: all 0.3s;
        }

        .tips-card:hover {
            transform: translateY(-4px);
            box-shadow: 4px 4px 0px 0px var(--primary);
        }

        .tips-img-box {
            height: 200px;
            position: relative;
        }

        .tips-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .tips-body {
            padding: 25px;
        }

        .tips-date {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .tips-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.4;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .tips-text {
            font-size: 14px;
            color: var(--on-surface-variant);
            line-height: 1.5;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .btn-tips-link {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 13px;
            color: var(--accent);
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: gap 0.2s;
        }

        .btn-tips-link:hover {
            color: var(--accent-hover);
            text-decoration: none;
            gap: 8px;
        }

        /* Lead Gen Section (Call to Action) */
        .cta-section {
            background-color: var(--primary);
            color: #ffffff;
            padding: 100px 80px;
            border-top: 4px solid var(--accent);
            border-bottom: 4px solid var(--accent);
        }

        @media (max-width: 767px) {
            .cta-section {
                padding: 60px 16px;
            }
        }

        /* Footer */
        .footer {
            background-color: #050d18;
            color: #94a3b8;
            padding: 80px 80px 40px;
            font-size: 14px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        @media (max-width: 767px) {
            .footer {
                padding: 40px 16px 20px;
            }
        }

        .footer-brand {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 20px;
            color: #ffffff;
            margin-bottom: 20px;
        }

        .footer-brand span {
            color: var(--accent);
        }

        .footer h5 {
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--accent);
        }

        .footer-contact-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 15px;
        }

        .footer-contact-item i {
            color: var(--accent);
            margin-top: 4px;
        }

        .footer-socials {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .footer-social-icon {
            width: 36px;
            height: 36px;
            background-color: rgba(255,255,255,0.05);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            transition: all 0.2s;
        }

        .footer-social-icon:hover {
            background-color: var(--accent);
            color: #ffffff;
            transform: translateY(-2px);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.05);
            margin-top: 60px;
            padding-top: 30px;
            text-align: center;
            font-size: 12px;
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
        <a class="navbar-brand" href="#home">
            <span class="brand-icon d-flex align-items-center justify-content-center" style="width:32px;height:32px;background:var(--accent);border-radius:var(--border-radius);">
                <i class="fas fa-hard-hat text-white" style="font-size:15px;"></i>
            </span>
            <span>MKM <span>CONSTRUCTION</span></span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item active"><a class="nav-link" href="#home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#services">Layanan</a></li>
                <li class="nav-item"><a class="nav-link" href="#portfolio">Portofolio</a></li>
                <li class="nav-item"><a class="nav-link" href="#tips">Tips & Trick</a></li>
                <li class="nav-item"><a class="nav-link" href="kalkulator.php"><i class="fas fa-calculator mr-1"></i> Kalkulator</a></li>
                <li class="nav-item ml-lg-3"><a class="nav-link btn-cta-nav px-3" href="login.php"><i class="fas fa-lock mr-1"></i> Login Portal</a></li>
            </ul>
        </div>
    </nav>

    <!-- HERO CAROUSEL -->
    <div id="heroCarousel" class="carousel slide hero-carousel" data-ride="carousel">
        <ol class="carousel-indicators" style="z-index: 10;">
            <?php foreach ($banners as $index => $banner): ?>
                <li data-target="#heroCarousel" data-slide-to="<?= $index ?>" class="<?= $index === 0 ? 'active' : '' ?>"></li>
            <?php endforeach; ?>
        </ol>
        <div class="carousel-inner">
            <?php if (empty($banners)): ?>
                <div class="carousel-item active">
                    <div class="carousel-img-overlay"></div>
                    <img class="carousel-img" src="https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=1200&q=80" alt="Construction Site">
                    <div class="carousel-caption-custom">
                        <h1>Membangun Dengan Integritas & Presisi</h1>
                        <p>PT. Mega Karya Modern menghadirkan konstruksi berkualitas tinggi dengan standar keandalan tinggi dan manajemen profesional.</p>
                        <a href="kalkulator.php" class="btn-premium mr-3">Kalkulator Konstruksi</a>
                        <a href="#services" class="btn-outline-premium">Pelajari Layanan</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($banners as $index => $banner): ?>
                    <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                        <div class="carousel-img-overlay"></div>
                        <img class="carousel-img" src="<?= sanitize($banner['image_url']) ?>" alt="<?= sanitize($banner['title']) ?>">
                        <div class="carousel-caption-custom">
                            <h1><?= sanitize($banner['title']) ?></h1>
                            <?php if ($banner['subtitle']): ?>
                                <p><?= sanitize($banner['subtitle']) ?></p>
                            <?php endif; ?>
                            <?php if ($banner['button_text'] && $banner['button_url']): ?>
                                <a href="<?= sanitize($banner['button_url']) ?>" class="btn-premium"><?= sanitize($banner['button_text']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <a class="carousel-control-prev" href="#heroCarousel" role="button" data-slide="prev" style="z-index: 10;">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="sr-only">Previous</span>
        </a>
        <a class="carousel-control-next" href="#heroCarousel" role="button" data-slide="next" style="z-index: 10;">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="sr-only">Next</span>
        </a>
    </div>

    <!-- SERVICES (LAYANAN) SECTION -->
    <section id="services" class="section-padding">
        <div class="container-fluid max-width-layout">
            <div class="section-title-container text-center">
                <span class="label-caps">Layanan Terbaik Kami</span>
                <h2 class="section-title justify-content-center d-flex flex-column align-items-center">Spesialisasi Konstruksi</h2>
            </div>
            
            <div class="row">
                <?php if (empty($services)): ?>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="service-card">
                            <div class="service-icon-box"><i class="fas fa-home"></i></div>
                            <h3>Pembangunan Residensial</h3>
                            <p>Konstruksi rumah tinggal mewah, villa, dan perumahan dengan material kokoh dan finishing premium.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="service-card">
                            <div class="service-icon-box"><i class="fas fa-building"></i></div>
                            <h3>Konstruksi Komersial</h3>
                            <p>Ruko, gedung kantor, dan fasilitas bisnis yang dioptimalkan untuk efisiensi ruang dan daya tahan struktural.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="service-card">
                            <div class="service-icon-box"><i class="fas fa-tools"></i></div>
                            <h3>Renovasi Struktural</h3>
                            <p>Perbaikan dan peningkatan kekuatan bangunan lama dengan analisis ketahanan gempa dan pembebanan modern.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="service-card">
                            <div class="service-icon-box"><i class="fas fa-drafting-line"></i></div>
                            <h3>Desain & Perencanaan</h3>
                            <p>Gambar kerja detail, perhitungan kekuatan struktur (RAB), dan perencanaan blueprint proyek konstruksi.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($services as $service): ?>
                        <div class="col-md-6 col-lg-3 mb-4">
                            <div class="service-card">
                                <div class="service-icon-box">
                                    <i class="fas <?= sanitize($service['icon'] ?: 'fa-hard-hat') ?>"></i>
                                </div>
                                <h3><?= sanitize($service['title']) ?></h3>
                                <p><?= nl2br(sanitize($service['description'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- PORTFOLIO SECTION -->
    <section id="portfolio" class="section-padding" style="background-color: #f1f5f9;">
        <div class="container-fluid max-width-layout">
            <div class="section-title-container text-center">
                <span class="label-caps">Portofolio Kerja</span>
                <h2 class="section-title justify-content-center d-flex flex-column align-items-center">Proyek Unggulan Kami</h2>
                
                <!-- Filters -->
                <div class="d-flex justify-content-center flex-wrap mt-4">
                    <button class="portfolio-filter-btn active" data-filter="all">Semua Proyek</button>
                    <button class="portfolio-filter-btn" data-filter="Residensial">Residensial</button>
                    <button class="portfolio-filter-btn" data-filter="Komersial">Komersial</button>
                    <button class="portfolio-filter-btn" data-filter="Infrastruktur">Infrastruktur</button>
                </div>
            </div>

            <div class="row portfolio-container">
                <?php if (empty($portfolios)): ?>
                    <div class="col-md-6 col-lg-4 portfolio-item" data-category="Residensial">
                        <div class="portfolio-card">
                            <span class="portfolio-badge">Residensial</span>
                            <div class="portfolio-img-container">
                                <img class="portfolio-img" src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=800&q=80" alt="Villa Mewah">
                                <div class="portfolio-overlay">
                                    <h4>Villa Mewah Golden City</h4>
                                    <p>Pembangunan villa 3 lantai dengan struktur beton bertulang K-300.</p>
                                    <span class="btn-outline-premium btn-sm py-1 px-3" style="font-size:12px;">Detail Proyek</span>
                                </div>
                            </div>
                            <div class="portfolio-card-info">
                                <h4>Villa Mewah Golden City</h4>
                                <p>Client: Bapak Budi Santoso</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4 portfolio-item" data-category="Komersial">
                        <div class="portfolio-card">
                            <span class="portfolio-badge">Komersial</span>
                            <div class="portfolio-img-container">
                                <img class="portfolio-img" src="https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=800&q=80" alt="Ruko Minimalis">
                                <div class="portfolio-overlay">
                                    <h4>Ruko Mega Komersial</h4>
                                    <p>Kompleks rumah toko modern 3 lantai di kawasan Bengkong Laut.</p>
                                    <span class="btn-outline-premium btn-sm py-1 px-3" style="font-size:12px;">Detail Proyek</span>
                                </div>
                            </div>
                            <div class="portfolio-card-info">
                                <h4>Ruko Mega Komersial</h4>
                                <p>Client: PT. Mega Property Group</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($portfolios as $portfolio): ?>
                        <div class="col-md-6 col-lg-4 portfolio-item" data-category="<?= sanitize($portfolio['category']) ?>">
                            <div class="portfolio-card">
                                <span class="portfolio-badge"><?= sanitize($portfolio['category']) ?></span>
                                <div class="portfolio-img-container">
                                    <img class="portfolio-img" src="<?= sanitize($portfolio['image_url']) ?>" alt="<?= sanitize($portfolio['title']) ?>">
                                    <div class="portfolio-overlay">
                                        <h4><?= sanitize($portfolio['title']) ?></h4>
                                        <p><?= sanitize($portfolio['description']) ?></p>
                                        <div style="font-size: 13px; color: #fff;" class="mb-2">
                                            <strong>Client:</strong> <?= sanitize($portfolio['client'] ?: '-') ?><br>
                                            <strong>Tanggal:</strong> <?= $portfolio['project_date'] ? formatDate($portfolio['project_date']) : '-' ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="portfolio-card-info">
                                    <h4><?= sanitize($portfolio['title']) ?></h4>
                                    <p>Client: <?= sanitize($portfolio['client'] ?: '-') ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- TIPS & TRICKS SECTION -->
    <section id="tips" class="section-padding">
        <div class="container-fluid max-width-layout">
            <div class="section-title-container text-center">
                <span class="label-caps">Informasi & Edukasi</span>
                <h2 class="section-title justify-content-center d-flex flex-column align-items-center">Tips & Trick Konstruksi</h2>
            </div>

            <div class="row">
                <?php if (empty($tips)): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="tips-card">
                            <div class="tips-img-box">
                                <img class="tips-img" src="https://images.unsplash.com/photo-1581094288338-2314dddb7ecc?auto=format&fit=crop&w=800&q=80" alt="Beton Cor">
                            </div>
                            <div class="tips-body">
                                <div class="tips-date"><i class="far fa-calendar-alt"></i> 12-Mei-2026 | Oleh: Ir. Hermawan</div>
                                <h4 class="tips-title">Cara Menghitung Kebutuhan Semen untuk Cor Beton Kolom</h4>
                                <p class="tips-text">Untuk beton standar K-225 (1:2:3), Anda membutuhkan sekitar 326 kg semen per meter kubik beton. Pastikan perbandingan volume air dan semen terkontrol agar beton tidak retak.</p>
                                <a href="#" class="btn-tips-link">Baca Selengkapnya <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($tips as $tip): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="tips-card">
                                <div class="tips-img-box">
                                    <img class="tips-img" src="<?= sanitize($tip['image_url']) ?>" alt="<?= sanitize($tip['title']) ?>">
                                </div>
                                <div class="tips-body">
                                    <div class="tips-date">
                                        <i class="far fa-calendar-alt"></i> <?= $tip['published_date'] ? formatDate($tip['published_date']) : '-' ?> | Oleh: <?= sanitize($tip['author'] ?: 'Admin') ?>
                                    </div>
                                    <h4 class="tips-title"><?= sanitize($tip['title']) ?></h4>
                                    <p class="tips-text"><?= sanitize($tip['excerpt'] ?: substr(strip_tags($tip['content']), 0, 150)) ?></p>
                                    <a href="#" class="btn-tips-link modal-tips-trigger" data-title="<?= sanitize($tip['title']) ?>" data-author="<?= sanitize($tip['author'] ?: 'Admin') ?>" data-date="<?= $tip['published_date'] ? formatDate($tip['published_date']) : '-' ?>" data-content="<?= sanitize($tip['content']) ?>" data-image="<?= sanitize($tip['image_url']) ?>" data-toggle="modal" data-target="#tipsDetailModal">Baca Selengkapnya <i class="fas fa-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- CALL TO ACTION SECTION -->
    <section class="cta-section text-center">
        <div class="container-fluid max-width-layout">
            <span class="label-caps" style="color:var(--accent);">Kalkulator Praktis</span>
            <h2 class="mb-3 font-weight-bold" style="font-size:36px; font-family:'Montserrat', sans-serif;">Ingin Estimasi Kebutuhan Material Anda?</h2>
            <p class="mx-auto mb-5" style="max-width: 700px; font-size: 16px; color:#cbd5e1; font-family: 'Work Sans', sans-serif;">
                Gunakan alat kalkulator konstruksi modern kami untuk menghitung secara instan kebutuhan bata, keramik, cat tembok, beton cor, plafon, dan rangka hollow. Sesuai standar mutu teknik sipil.
            </p>
            <a href="kalkulator.php" class="btn-premium"><i class="fas fa-calculator mr-2"></i> Buka Kalkulator Konstruksi</a>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container-fluid max-width-layout">
            <div class="row">
                <div class="col-lg-4 mb-5 mb-lg-0">
                    <div class="footer-brand">
                        <span>PT. MEGA KARYA MODERN</span>
                    </div>
                    <p class="mb-4" style="line-height: 1.6;">PT. Mega Karya Modern berkomitmen penuh dalam menyediakan solusi konstruksi yang andal, kokoh, dan berpresisi tinggi. Kami bangga membangun masa depan Indonesia yang lebih kuat.</p>
                    <div class="footer-socials">
                        <a href="#" class="footer-social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="footer-social-icon"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="footer-social-icon"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 offset-lg-1 mb-4 mb-md-0">
                    <h5>Navigasi</h5>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Layanan Kami</a></li>
                        <li><a href="#portfolio">Portofolio Proyek</a></li>
                        <li><a href="#tips">Tips & Trick</a></li>
                        <li><a href="kalkulator.php">Kalkulator Bangunan</a></li>
                        <li><a href="login.php">Login Portal</a></li>
                    </ul>
                </div>
                <div class="col-md-6 col-lg-4">
                    <h5>Kontak Kami</h5>
                    <div class="footer-contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Ruko Golden City, Bengkong Laut, Blok I no 10 RT 3 RW 4, Batam, Kepulauan Riau 29458</span>
                    </div>
                    <div class="footer-contact-item">
                        <i class="fas fa-phone-alt"></i>
                        <span>+62 812-7417-1386</span>
                    </div>
                    <div class="footer-contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>megakaryamodern@gmail.com</span>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p class="mb-0">&copy; <?= date('Y') ?> PT. Mega Karya Modern. All rights reserved. Managed with MKM Procurement.</p>
            </div>
        </div>
    </footer>

    <!-- TIPS DETAIL MODAL -->
    <div class="modal fade" id="tipsDetailModal" tabindex="-1" aria-labelledby="tipsDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--border-radius); border: 2px solid var(--primary);">
                <div class="modal-header" style="background-color: var(--primary); color: #ffffff; border-bottom: 2px solid var(--accent);">
                    <h5 class="modal-title" id="tipsDetailModalLabel" style="font-weight:700;">Detail Tips</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <img id="modalTipsImage" src="" class="img-fluid w-100 mb-3" style="border-radius: var(--border-radius); height: 350px; object-fit: cover; border: 1px solid var(--border-color);" alt="Tips Image">
                    <div class="tips-date mb-3" id="modalTipsMeta" style="font-size:13px;"><i class="far fa-calendar-alt"></i> </div>
                    <h3 class="mb-3 font-weight-bold" id="modalTipsTitle" style="color:var(--primary); font-size: 24px;"></h3>
                    <hr>
                    <div id="modalTipsContent" style="font-size: 15px; color: var(--on-surface-variant); line-height: 1.7; white-space: pre-line;"></div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal" style="border-radius: var(--border-radius);">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT & LIBS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Portfolio Filter functionality
            $('.portfolio-filter-btn').click(function() {
                var filterValue = $(this).attr('data-filter');
                $('.portfolio-filter-btn').removeClass('active');
                $(this).addClass('active');

                if (filterValue == 'all') {
                    $('.portfolio-item').show(300);
                } else {
                    $('.portfolio-item').hide();
                    $('.portfolio-item[data-category="' + filterValue + '"]').show(300);
                }
            });

            // Populate Tips Modal
            $('.modal-tips-trigger').click(function() {
                var title = $(this).attr('data-title');
                var author = $(this).attr('data-author');
                var date = $(this).attr('data-date');
                var content = $(this).attr('data-content');
                var image = $(this).attr('data-image');

                $('#modalTipsTitle').text(title);
                $('#modalTipsMeta').html('<i class="far fa-calendar-alt"></i> ' + date + ' | Oleh: ' + author);
                $('#modalTipsContent').text(content);
                $('#modalTipsImage').attr('src', image);
            });
        });
    </script>
</body>
</html>