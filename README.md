# MKM Procurement & Landing Page System

Sistem Manajemen Pengadaan (E-Procurement) Internal sekaligus Halaman Depan Publik (Landing Page) & Kalkulator Konstruksi untuk **PT. Mega Karya Modern**.

Sistem ini dirancang untuk memadukan profil bisnis perusahaan yang dapat diakses publik dengan sistem manajemen pengadaan barang (*procurement*), gudang, keuangan, dan kontrol proyek secara internal.

---

## 🚀 Fitur Utama

### 1. Halaman Publik & Utilitas Sipil
*   **Landing Page Dinamis**: Menampilkan Hero Banner, spesialisasi Layanan, Portofolio Proyek unggulan dengan sistem filter, serta artikel Tips & Trick konstruksi. Semua konten di-load secara dinamis dari database.
*   **Kalkulator Bangunan Interaktif**: Alat hitung estimasi kebutuhan bahan bangunan secara *real-time* berbasis Client-side Javascript:
    *   *Kalkulator Bata*: Menghitung volume dinding, kebutuhan unit bata merah/batako/hebel, spesi semen pasir, atau semen instan hebel.
    *   *Kalkulator Keramik*: Menghitung kebutuhan box ubin berdasarkan luas ruangan dan cadangan potongan (*wastage*).
    *   *Kalkulator Cat*: Estimasi liter cat tembok berdasarkan jumlah lapisan pengecatan.
    *   *Kalkulator Cor Beton*: Perhitungan semen (sak 50kg), pasir (m³), dan batu split (m³) untuk mutu cor beton K-175 dan K-225.
    *   *Kalkulator Plafon*: Estimasi lembar gypsum dan panjang rangka besi hollow 4x4 & 2x4.

### 2. CMS Landing Page (Admin Panel)
*   **Kelola Banner**: Pengaturan banner karosel utama, teks CTA, link, serta upload gambar secara mandiri.
*   **Kelola Layanan**: CRUD keahlian utama konstruksi dengan pustaka ikon FontAwesome.
*   **Kelola Portofolio**: Dokumentasi proyek lengkap dengan detail nama klien, kategori, tanggal selesai, dan unggahan foto.
*   **Kelola Tips & Trick**: Penulisan artikel edukatif konstruksi lengkap dengan ringkasan (*excerpt*) otomatis.

### 3. Sistem Internal E-Procurement & Kontrol Proyek
*   **Material Request (MR)**: Pengajuan permintaan material proyek oleh Project Manager ke tim logistik/gudang.
*   **Purchase Order (PO)**: Penerbitan pesanan pembelian ke vendor dengan sistem persetujuan bertingkat (*Approval*).
*   **Penerimaan Barang**: Pencatatan barang masuk di gudang/lokasi proyek berdasarkan Surat Jalan vendor.
*   **Kontrol Stok & Gudang**: Manajemen stok barang minimum, peringatan batas stok (*alerts*), dan transfer material antar proyek.
*   **Sales & Finance**: Pembuatan Quotation untuk customer, penagihan Invoice, Nota Klaim karyawan, Pembayaran Vendor, Penerimaan Kas, dan Buku Kas Utama (*General Ledger*).
*   **Manajemen Karyawan & Timesheet**: Penginputan jam kerja lapangan dan sistem persetujuan lembur/kehadiran.

---

## 🛠️ Spesifikasi Teknologi
*   **Core Logic**: PHP (OOP & Procedural dengan PDO Security Prepared Statements)
*   **Database**: MySQL / MariaDB
*   **Framework UI Admin**: AdminLTE 3 (Bootstrap 4 & jQuery)
*   **Styling Depan**: Custom CSS Modern (*Iron & Oak Foundation* design system)
*   **Library Tambahan**: Select2, DataTables, SweetAlert2, FontAwesome 5

---

## ⚙️ Petunjuk Pemasangan

### Prasyarat:
*   PHP Versi 7.4 atau lebih baru (Direkomendasikan PHP 8.x)
*   Web Server (Apache/Nginx) - Sangat direkomendasikan menggunakan **Laragon** atau **XAMPP**
*   MySQL Database Server

### Langkah-Langkah:
1.  **Clone Repository & Letakkan di Folder Web Server**:
    Letakkan folder project ini di direktori `C:/laragon/www/newmega` atau `htdocs/newmega`.

2.  **Konfigurasi Database**:
    *   Nyalakan MySQL di Laragon/XAMPP.
    *   Buat database baru dengan nama `procurementDB`.
    *   Import file database dasar: [schema.sql](file:///c:/laragon/www/newmega/database/schema.sql) ke dalam database tersebut.

3.  **Jalankan Migrasi Tambahan**:
    Jalankan script migrasi landing page via command line untuk melengkapi struktur tabel dan hak akses default:
    ```bash
    php database/migrate_landing_page.php
    ```

4.  **Sesuaikan File Konfigurasi**:
    Buka file [config.php](file:///c:/laragon/www/newmega/config.php) dan sesuaikan detail koneksi database jika Anda tidak menggunakan konfigurasi default (`root` / password kosong).

5.  **Akses Aplikasi di Browser**:
    *   Buka URL: `http://localhost/newmega/`
    *   Landing Page publik akan ter-load di halaman depan.
    *   Untuk masuk ke panel admin, klik **Login Portal** di navigasi atas (atau akses `http://localhost/newmega/login.php`).

### Akun Login Bawaan (Default):
*   **Username**: `admin`
*   **Password**: `admin123`
*   **Role**: Super Administrator

---

## 📁 Struktur Direktori Utama
*   `index.php` - Landing Page publik
*   `login.php` - Halaman masuk administrator
*   `kalkulator.php` - Fitur Kalkulator Bangunan publik
*   `config.php` - Konfigurasi sistem dan koneksi database
*   `includes/` - File bersama (header, footer, sidebar, fungsi global helper, middleware auth)
*   `database/` - File SQL skema database dan script migrasi PHP
*   `assets/` - Aset statis (CSS custom, JS, gambar, data unggahan)
*   `modules/` - Modul internal (dashboard, auth, cms, master data, procurement, warehouse, sales, finance, reports)
*   `uploads/` - Lokasi berkas dinamis terunggah

---
*Dikembangkan oleh PT. Mega Karya Modern & didukung oleh MKM Procurement System.*
