<?php
/**
 * Building Calculator Page
 * PT. Mega Karya Modern
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Kalkulator Konstruksi PT. Mega Karya Modern - Hitung Kebutuhan Bahan Bangunan Anda Secara Praktis & Akurat.">
    <title>Kalkulator Bangunan | PT. Mega Karya Modern</title>

    <!-- Google Fonts: Montserrat & Work Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Font Awesome 5 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
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

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: var(--primary);
        }

        .label-caps {
            font-family: 'Work Sans', sans-serif;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--accent);
        }

        /* Navbar */
        .navbar-custom {
            background-color: var(--primary) !important;
            padding: 15px 80px;
            border-bottom: 2px solid var(--accent);
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

        .navbar-custom .nav-link:hover {
            color: var(--accent) !important;
        }

        /* Layout */
        .page-header {
            background-color: var(--primary);
            color: #ffffff;
            padding: 80px 80px 60px;
            border-bottom: 4px solid var(--accent);
            text-align: center;
            position: relative;
        }

        @media (max-width: 767px) {
            .page-header {
                padding: 40px 16px 30px;
            }
        }

        .page-header h1 {
            color: #ffffff;
            font-size: 40px;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 15px;
        }

        @media (max-width: 767px) {
            .page-header h1 {
                font-size: 28px;
            }
        }

        .page-header p {
            color: #cbd5e1;
            font-size: 16px;
            max-width: 600px;
            margin: 0 auto;
        }

        .calculator-container {
            padding: 60px 80px;
            max-width: 1280px;
            margin: 0 auto;
        }

        @media (max-width: 1023px) {
            .calculator-container {
                padding: 40px 20px;
            }
        }

        @media (max-width: 767px) {
            .calculator-container {
                padding: 30px 16px;
            }
        }

        /* Tab Navigation - Corporate styling */
        .nav-tabs-custom {
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 40px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .nav-tabs-custom .nav-link {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--on-surface-variant);
            border: 1px solid var(--border-color);
            background: rgba(224, 227, 229, 0.5);
            padding: 12px 20px;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            margin-bottom: -2px;
            transition: all 0.2s;
        }

        .nav-tabs-custom .nav-link:hover {
            background: rgba(224, 227, 229, 0.8);
            color: var(--primary);
        }

        .nav-tabs-custom .nav-link.active {
            background: var(--surface);
            color: var(--primary);
            border-color: var(--border-color) var(--border-color) var(--surface);
            border-bottom-width: 2px;
            box-shadow: none;
        }

        /* Card panels */
        .calc-panel {
            background-color: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 40px;
            display: none;
            transition: opacity 0.3s;
        }

        .calc-panel.active {
            display: block;
        }

        @media (max-width: 767px) {
            .calc-panel {
                padding: 20px;
            }
        }

        /* Form Controls matching design.md */
        .form-group label {
            font-weight: 600;
            font-size: 14px;
            color: var(--primary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            height: 48px;
            font-size: 16px;
            font-family: 'Work Sans', sans-serif;
            padding: 10px 16px;
            color: var(--on-surface);
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            box-shadow: none;
            border-color: var(--border-color);
            border-bottom: 2px solid var(--accent); /* Focus bottom accent as per design.md */
        }

        .btn-calc {
            background-color: var(--accent);
            color: #ffffff;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 14px 28px;
            border-radius: var(--border-radius);
            border: none;
            transition: all 0.2s;
            width: 100%;
        }

        .btn-calc:hover {
            background-color: var(--accent-hover);
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Results Box - High Contrast */
        .results-box {
            background-color: var(--primary);
            color: #ffffff;
            border-radius: var(--border-radius);
            padding: 35px;
            height: 100%;
            border: 1px solid rgba(255,255,255,0.1);
        }

        @media (max-width: 767px) {
            .results-box {
                margin-top: 30px;
                padding: 20px;
            }
        }

        .results-box h4 {
            color: var(--accent);
            font-size: 18px;
            font-weight: 700;
            border-bottom: 1px solid rgba(252, 148, 48, 0.3);
            padding-bottom: 15px;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            font-size: 15px;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.1);
            padding-bottom: 12px;
        }

        .result-item:last-child {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }

        .result-val {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 18px;
            color: #ffffff;
        }

        .result-val span {
            color: var(--accent);
        }

        /* Alert note */
        .calc-alert {
            background-color: rgba(252, 148, 48, 0.08);
            border: 1px solid rgba(252, 148, 48, 0.2);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-top: 25px;
            font-size: 13px;
            color: var(--on-surface-variant);
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .calc-alert i {
            color: var(--accent);
            margin-top: 3px;
        }

        /* Footer */
        .footer {
            background-color: #050d18;
            color: #94a3b8;
            padding: 40px 80px;
            font-size: 14px;
            text-align: center;
            border-top: 4px solid var(--accent);
        }

        @media (max-width: 767px) {
            .footer {
                padding: 30px 16px;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <a class="navbar-brand" href="index.php">
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
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php#services">Layanan</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php#portfolio">Portofolio</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php#tips">Tips & Trick</a></li>
                <li class="nav-item active"><a class="nav-link" href="kalkulator.php"><i class="fas fa-calculator mr-1"></i> Kalkulator</a></li>
                <li class="nav-item ml-lg-3"><a class="nav-link btn-cta-nav px-3" href="login.php"><i class="fas fa-lock mr-1"></i> Login Portal</a></li>
            </ul>
        </div>
    </nav>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <span class="label-caps" style="color:var(--accent);">Tools Konstruksi</span>
        <h1>Kalkulator Bangunan & Material</h1>
        <p>Estimasi kebutuhan material bata, cat, semen pasir cor, keramik, serta plafon untuk proyek Anda secara instan dan presisi.</p>
    </div>

    <!-- CALCULATOR CONTENT -->
    <div class="calculator-container">
        <!-- Tab Navigation -->
        <div class="nav-tabs-custom">
            <a href="#" class="nav-link active" data-tab="tab-bata"><i class="fas fa-cubes mr-1"></i> Bata & Dinding</a>
            <a href="#" class="nav-link" data-tab="tab-keramik"><i class="fas fa-th mr-1"></i> Keramik & Ubin</a>
            <a href="#" class="nav-link" data-tab="tab-cat"><i class="fas fa-paint-roller mr-1"></i> Cat Tembok</a>
            <a href="#" class="nav-link" data-tab="tab-beton"><i class="fas fa-mortar-pestle mr-1"></i> Cor Beton</a>
            <a href="#" class="nav-link" data-tab="tab-plafon"><i class="fas fa-align-justify mr-1"></i> Plafon & Hollow</a>
        </div>

        <!-- 1. BATA & DINDING PANEL -->
        <div id="tab-bata" class="calc-panel active">
            <div class="row">
                <div class="col-md-7">
                    <h3 class="mb-4">Kalkulator Kebutuhan Bata</h3>
                    <form id="form-bata" oninput="hitungBata()">
                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label for="bata-panjang">Panjang Dinding (m)</label>
                                <input type="number" step="0.1" min="0" id="bata-panjang" class="form-control" placeholder="Contoh: 12" value="10">
                            </div>
                            <div class="col-sm-6 form-group">
                                <label for="bata-tinggi">Tinggi Dinding (m)</label>
                                <input type="number" step="0.1" min="0" id="bata-tinggi" class="form-control" placeholder="Contoh: 3.5" value="3">
                            </div>
                        </div>
                        <div class="form-group mt-3">
                            <label for="bata-jenis">Jenis Bata / Dinding</label>
                            <select id="bata-jenis" class="form-control">
                                <option value="bata_merah" selected>Bata Merah (Ukuran Standar - ±70 pcs/m²)</option>
                                <option value="batako">Batako (Ukuran 40x20x10 cm - ±12.5 pcs/m²)</option>
                                <option value="hebel_75">Bata Ringan Hebel 7.5 cm (60x20x7.5 cm - ±8.3 pcs/m²)</option>
                                <option value="hebel_10">Bata Ringan Hebel 10 cm (60x20x10 cm - ±8.3 pcs/m²)</option>
                            </select>
                        </div>
                        <div class="form-group mt-3">
                            <label for="bata-waste">Wastage / Cadangan Kehilangan (%)</label>
                            <input type="number" min="0" max="30" id="bata-waste" class="form-control" value="5">
                        </div>
                    </form>
                    <div class="calc-alert">
                        <i class="fas fa-info-circle"></i>
                        <span><strong>Catatan:</strong> Perhitungan di atas mencakup ketebalan mortar spesi (siar). Bata ringan dihitung menggunakan semen mortar instan khusus hebel.</span>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="results-box">
                        <h4>Hasil Estimasi</h4>
                        <div class="result-item">
                            <span>Luas Total Dinding</span>
                            <div class="result-val"><span id="res-bata-luas">30.00</span> m²</div>
                        </div>
                        <div class="result-item">
                            <span>Kebutuhan Utama</span>
                            <div class="result-val"><span id="res-bata-jumlah">2.100</span> Pcs</div>
                        </div>
                        <div class="result-item">
                            <span>Estimasi Mortar / Semen</span>
                            <div class="result-val"><span id="res-bata-semen">3</span> Sak</div>
                        </div>
                        <div class="result-item">
                            <span>Estimasi Pasir (Bila Non-Hebel)</span>
                            <div class="result-val"><span id="res-bata-pasir">1.2</span> m³</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. KERAMIK PANEL -->
        <div id="tab-keramik" class="calc-panel">
            <div class="row">
                <div class="col-md-7">
                    <h3 class="mb-4">Kalkulator Kebutuhan Keramik</h3>
                    <form id="form-keramik" oninput="hitungKeramik()">
                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label for="keramik-panjang">Panjang Ruangan (m)</label>
                                <input type="number" step="0.1" min="0" id="keramik-panjang" class="form-control" placeholder="Contoh: 6" value="5">
                            </div>
                            <div class="col-sm-6 form-group">
                                <label for="keramik-lebar">Lebar Ruangan (m)</label>
                                <input type="number" step="0.1" min="0" id="keramik-lebar" class="form-control" placeholder="Contoh: 4" value="4">
                            </div>
                        </div>
                        <div class="form-group mt-3">
                            <label for="keramik-ukuran">Ukuran Keramik</label>
                            <select id="keramik-ukuran" class="form-control">
                                <option value="30" selected>30 x 30 cm (11 pcs/box - 0.99 m²)</option>
                                <option value="40">40 x 40 cm (6 pcs/box - 0.96 m²)</option>
                                <option value="50">50 x 50 cm (4 pcs/box - 1.00 m²)</option>
                                <option value="60">60 x 60 cm (4 pcs/box - 1.44 m²)</option>
                            </select>
                        </div>
                        <div class="form-group mt-3">
                            <label for="keramik-waste">Wastage / Cadangan Potongan (%)</label>
                            <input type="number" min="0" max="30" id="keramik-waste" class="form-control" value="5">
                        </div>
                    </form>
                    <div class="calc-alert">
                        <i class="fas fa-info-circle"></i>
                        <span><strong>Tip Pemasangan:</strong> Cadangan wastage 5% - 10% sangat disarankan karena adanya pemotongan keramik di area sudut ruangan atau jika dipasang secara diagonal.</span>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="results-box">
                        <h4>Hasil Estimasi</h4>
                        <div class="result-item">
                            <span>Luas Lantai</span>
                            <div class="result-val"><span id="res-keramik-luas">20.00</span> m²</div>
                        </div>
                        <div class="result-item">
                            <span>Total Box Dibutuhkan</span>
                            <div class="result-val"><span id="res-keramik-box">22</span> Box</div>
                        </div>
                        <div class="result-item">
                            <span>Jumlah Keping Ubin</span>
                            <div class="result-val"><span id="res-keramik-keping">234</span> Pcs</div>
                        </div>
                        <div class="result-item">
                            <span>Semen Perekat Ubin</span>
                            <div class="result-val"><span id="res-keramik-semen">4</span> Sak (40kg)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. CAT TEMBOK PANEL -->
        <div id="tab-cat" class="calc-panel">
            <div class="row">
                <div class="col-md-7">
                    <h3 class="mb-4">Kalkulator Kebutuhan Cat Tembok</h3>
                    <form id="form-cat" oninput="hitungCat()">
                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label for="cat-panjang">Total Panjang Dinding (m)</label>
                                <input type="number" step="0.1" min="0" id="cat-panjang" class="form-control" placeholder="Contoh: 15" value="12">
                            </div>
                            <div class="col-sm-6 form-group">
                                <label for="cat-tinggi">Tinggi Dinding (m)</label>
                                <input type="number" step="0.1" min="0" id="cat-tinggi" class="form-control" placeholder="Contoh: 3" value="3">
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-sm-6 form-group">
                                <label for="cat-lapis">Jumlah Lapisan Cat</label>
                                <select id="cat-lapis" class="form-control">
                                    <option value="1">1 Lapis (Sentuhan Ringan)</option>
                                    <option value="2" selected>2 Lapis (Standar Rekomendasi)</option>
                                    <option value="3">3 Lapis (Warna Gelap / Dinding Baru)</option>
                                </select>
                            </div>
                            <div class="col-sm-6 form-group">
                                <label for="cat-sebar">Daya Sebar Cat (m²/Liter)</label>
                                <input type="number" step="0.5" min="1" id="cat-sebar" class="form-control" value="12">
                            </div>
                        </div>
                    </form>
                    <div class="calc-alert">
                        <i class="fas fa-info-circle"></i>
                        <span><strong>Info Teknis:</strong> Standar daya sebar cat interior berkualitas berkisar antara 10 - 12 m² per liter untuk satu kali pelapisan. Lapisan kedua mutlak diperlukan untuk hasil warna merata.</span>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="results-box">
                        <h4>Hasil Estimasi</h4>
                        <div class="result-item">
                            <span>Luas Permukaan Dinding</span>
                            <div class="result-val"><span id="res-cat-luas">36.00</span> m²</div>
                        </div>
                        <div class="result-item">
                            <span>Total Volume Cat</span>
                            <div class="result-val"><span id="res-cat-liter">6.00</span> Liter</div>
                        </div>
                        <div class="result-item">
                            <span>Kebutuhan Galon (2.5L)</span>
                            <div class="result-val"><span id="res-cat-galon">3</span> Galon</div>
                        </div>
                        <div class="result-item">
                            <span>Atau Kebutuhan Pail (20L)</span>
                            <div class="result-val"><span id="res-cat-pail">1</span> Pail</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. COR BETON PANEL -->
        <div id="tab-beton" class="calc-panel">
            <div class="row">
                <div class="col-md-7">
                    <h3 class="mb-4">Kalkulator Cor Beton</h3>
                    <form id="form-beton" oninput="hitungBeton()">
                        <div class="row">
                            <div class="col-sm-4 form-group">
                                <label for="beton-panjang">Panjang Area (m)</label>
                                <input type="number" step="0.1" min="0" id="beton-panjang" class="form-control" placeholder="Contoh: 6" value="5">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label for="beton-lebar">Lebar Area (m)</label>
                                <input type="number" step="0.1" min="0" id="beton-lebar" class="form-control" placeholder="Contoh: 4" value="4">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label for="beton-tebal">Tebal Cor (cm)</label>
                                <input type="number" step="1" min="0" id="beton-tebal" class="form-control" placeholder="Contoh: 12" value="10">
                            </div>
                        </div>
                        <div class="form-group mt-3">
                            <label for="beton-mutu">Mutu Beton (Standard Perbandingan)</label>
                            <select id="beton-mutu" class="form-control">
                                <option value="k175" selected>Beton K-175 / Setara (1 Semen : 2 Pasir : 3 Kerikil)</option>
                                <option value="k225">Beton K-225 / Konstruktif Struktural (326 kg Semen/m³)</option>
                            </select>
                        </div>
                    </form>
                    <div class="calc-alert">
                        <i class="fas fa-info-circle"></i>
                        <span><strong>Aturan Campuran:</strong> Untuk K-175, perbandingan volume yang digunakan adalah 1:2:3. Sedangkan untuk K-225, penghitungan bahan didasarkan pada standar SNI Sipil per m³ beton segar.</span>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="results-box">
                        <h4>Hasil Estimasi</h4>
                        <div class="result-item">
                            <span>Volume Beton Cor</span>
                            <div class="result-val"><span id="res-beton-volume">2.00</span> m³</div>
                        </div>
                        <div class="result-item">
                            <span>Semen (Sak 50kg)</span>
                            <div class="result-val"><span id="res-beton-semen">13</span> Sak</div>
                        </div>
                        <div class="result-item">
                            <span>Pasir Beton</span>
                            <div class="result-val"><span id="res-beton-pasir">1.1</span> m³</div>
                        </div>
                        <div class="result-item">
                            <span>Kerikil / Batu Pecah</span>
                            <div class="result-val"><span id="res-beton-kerikil">1.6</span> m³</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. PLAFON & HOLLOW PANEL -->
        <div id="tab-plafon" class="calc-panel">
            <div class="row">
                <div class="col-md-7">
                    <h3 class="mb-4">Kalkulator Plafon Gypsum & Hollow</h3>
                    <form id="form-plafon" oninput="hitungPlafon()">
                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label for="plafon-panjang">Panjang Ruangan (m)</label>
                                <input type="number" step="0.1" min="0" id="plafon-panjang" class="form-control" placeholder="Contoh: 6" value="5">
                            </div>
                            <div class="col-sm-6 form-group">
                                <label for="plafon-lebar">Lebar Ruangan (m)</label>
                                <input type="number" step="0.1" min="0" id="plafon-lebar" class="form-control" placeholder="Contoh: 4" value="4">
                            </div>
                        </div>
                    </form>
                    <div class="calc-alert">
                        <i class="fas fa-info-circle"></i>
                        <span><strong>Standard Gypsum:</strong> Ukuran standar 1 lembar gypsum board adalah 1.2 x 2.4 meter (2.88 m²). Penghitungan rangka hollow menggunakan kombinasi grid ukuran 60x60 cm.</span>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="results-box">
                        <h4>Hasil Estimasi</h4>
                        <div class="result-item">
                            <span>Luas Plafon</span>
                            <div class="result-val"><span id="res-plafon-luas">20.00</span> m²</div>
                        </div>
                        <div class="result-item">
                            <span>Lembar Gypsum (1.2x2.4)</span>
                            <div class="result-val"><span id="res-plafon-gypsum">8</span> Lembar</div>
                        </div>
                        <div class="result-item">
                            <span>Besi Hollow 4x4 cm (Batang 4m)</span>
                            <div class="result-val"><span id="res-plafon-h44">6</span> Batang</div>
                        </div>
                        <div class="result-item">
                            <span>Besi Hollow 2x4 cm (Batang 4m)</span>
                            <div class="result-val"><span id="res-plafon-h24">14</span> Batang</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p class="mb-0">&copy; <?= date('Y') ?> PT. Mega Karya Modern. All rights reserved. Managed with MKM Procurement.</p>
    </footer>

    <!-- JAVASCRIPT FOR DYNAMIC tabs & CALCS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Tab switcher
            $('.nav-tabs-custom .nav-link').click(function(e) {
                e.preventDefault();
                $('.nav-tabs-custom .nav-link').removeClass('active');
                $(this).addClass('active');

                var tabId = $(this).attr('data-tab');
                $('.calc-panel').removeClass('active');
                $('#' + tabId).addClass('active');
            });

            // Initial calculations
            hitungBata();
            hitungKeramik();
            hitungCat();
            hitungBeton();
            hitungPlafon();
        });

        // 1. Hitung Bata
        function hitungBata() {
            var panjang = parseFloat($('#bata-panjang').val()) || 0;
            var tinggi = parseFloat($('#bata-tinggi').val()) || 0;
            var jenis = $('#bata-jenis').val();
            var waste = parseFloat($('#bata-waste').val()) || 0;

            var luas = panjang * tinggi;
            $('#res-bata-luas').text(luas.toFixed(2));

            var koefisien = 70; // bata merah default
            var semenInstanHebel = 0; // sak per m2
            var pasirVol = 0; // m3
            var semenSakNormal = 0; // sak 50kg

            if (jenis === 'batako') {
                koefisien = 12.5;
                semenSakNormal = luas * 0.15; // 0.15 sak/m2
                pasirVol = luas * 0.015; // 0.015 m3/m2
            } else if (jenis === 'hebel_75') {
                koefisien = 8.33;
                semenInstanHebel = luas / 10; // 1 sak mortar per 10m2
            } else if (jenis === 'hebel_10') {
                koefisien = 8.33;
                semenInstanHebel = luas / 7.5; // 1 sak mortar per 7.5m2
            } else {
                // bata merah
                koefisien = 70;
                semenSakNormal = luas * 0.2; // 0.2 sak/m2
                pasirVol = luas * 0.04; // 0.04 m3/m2
            }

            var jumlah = Math.ceil(luas * koefisien * (1 + waste / 100));
            $('#res-bata-jumlah').text(jumlah.toLocaleString('id-ID'));

            if (jenis.indexOf('hebel') !== -1) {
                $('#res-bata-semen').text(Math.ceil(semenInstanHebel) + " Sak (Mortar)");
                $('#res-bata-pasir').text("-");
            } else {
                $('#res-bata-semen').text(Math.ceil(semenSakNormal) + " Sak (50kg)");
                $('#res-bata-pasir').text(pasirVol.toFixed(1) + " m³");
            }
        }

        // 2. Hitung Keramik
        function hitungKeramik() {
            var panjang = parseFloat($('#keramik-panjang').val()) || 0;
            var lebar = parseFloat($('#keramik-lebar').val()) || 0;
            var ukuran = $('#keramik-ukuran').val();
            var waste = parseFloat($('#keramik-waste').val()) || 0;

            var luas = panjang * lebar;
            $('#res-keramik-luas').text(luas.toFixed(2));

            var coverage = 1.0;
            var kepingPerBox = 4;

            if (ukuran === '30') {
                coverage = 0.99;
                kepingPerBox = 11;
            } else if (ukuran === '40') {
                coverage = 0.96;
                kepingPerBox = 6;
            } else if (ukuran === '50') {
                coverage = 1.00;
                kepingPerBox = 4;
            } else if (ukuran === '60') {
                coverage = 1.44;
                kepingPerBox = 4;
            }

            var totalLuasAman = luas * (1 + waste / 100);
            var box = Math.ceil(totalLuasAman / coverage);
            var keping = box * kepingPerBox;
            var semen = Math.ceil(luas * 0.2); // approx 1 sak (40kg) per 5m2 = 0.2 sak/m2

            $('#res-keramik-box').text(box);
            $('#res-keramik-keping').text(keping.toLocaleString('id-ID'));
            $('#res-keramik-semen').text(semen);
        }

        // 3. Hitung Cat
        function hitungCat() {
            var panjang = parseFloat($('#cat-panjang').val()) || 0;
            var tinggi = parseFloat($('#cat-tinggi').val()) || 0;
            var lapis = parseInt($('#cat-lapis').val()) || 2;
            var sebar = parseFloat($('#cat-sebar').val()) || 12;

            var luas = panjang * tinggi;
            $('#res-cat-luas').text(luas.toFixed(2));

            var totalLuasLapis = luas * lapis;
            var totalLiter = totalLuasLapis / sebar;

            // Galon = 2.5 liter, Pail = 20 liter
            var galon = Math.ceil(totalLiter / 2.5);
            var pail = Math.ceil(totalLiter / 20);

            $('#res-cat-liter').text(totalLiter.toFixed(2));
            $('#res-cat-galon').text(galon);
            $('#res-cat-pail').text(pail);
        }

        // 4. Hitung Cor Beton
        function hitungBeton() {
            var panjang = parseFloat($('#beton-panjang').val()) || 0;
            var lebar = parseFloat($('#beton-lebar').val()) || 0;
            var tebal = parseFloat($('#beton-tebal').val()) || 0;
            var mutu = $('#beton-mutu').val();

            var volume = panjang * lebar * (tebal / 100);
            $('#res-beton-volume').text(volume.toFixed(2));

            var semenSak = 0;
            var pasirVol = 0;
            var kerikilVol = 0;

            if (mutu === 'k225') {
                // SNI: Semen: 326 kg, Pasir: 0.54 m3, Kerikil: 0.82 m3 per m3 beton
                semenSak = (volume * 326) / 50; // sak 50kg
                pasirVol = volume * 0.54;
                kerikilVol = volume * 0.82;
            } else {
                // K175 (1:2:3 approx per m3: Semen: 276 kg, Pasir: 0.58 m3, Kerikil: 0.87 m3)
                semenSak = (volume * 276) / 50;
                pasirVol = volume * 0.58;
                kerikilVol = volume * 0.87;
            }

            $('#res-beton-semen').text(Math.ceil(semenSak));
            $('#res-beton-pasir').text(pasirVol.toFixed(1));
            $('#res-beton-kerikil').text(kerikilVol.toFixed(1));
        }

        // 5. Hitung Plafon
        function hitungPlafon() {
            var panjang = parseFloat($('#plafon-panjang').val()) || 0;
            var lebar = parseFloat($('#plafon-lebar').val()) || 0;

            var luas = panjang * lebar;
            $('#res-plafon-luas').text(luas.toFixed(2));

            // Gypsum sheet standard 2.88 m2. Wastage 5%
            var gypsum = Math.ceil((luas / 2.88) * 1.05);

            // Hollow formula (approx rods of 4m)
            // Hollow 4x4 = Area * 1.1 meters -> rods = (Area * 1.1) / 4
            // Hollow 2x4 = Area * 2.8 meters -> rods = (Area * 2.8) / 4
            var hollow44 = Math.ceil((luas * 1.1) / 4);
            var hollow24 = Math.ceil((luas * 2.8) / 4);

            $('#res-plafon-gypsum').text(gypsum);
            $('#res-plafon-h44').text(hollow44);
            $('#res-plafon-h24').text(hollow24);
        }
    </script>
</body>
</html>
