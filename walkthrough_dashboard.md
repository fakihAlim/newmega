# Walkthrough: Dashboard Grafik Interaktif (Executive Dashboard)

Fitur **Dashboard Grafik Interaktif (Executive Dashboard)** telah sukses diimplementasikan dan diuji di branch `dashboard`. Fitur ini menambahkan visualisasi data keuangan, progres anggaran proyek, dan distribusi pengeluaran material secara real-time bagi peran eksekutif (`super_admin`, `finance`, dan `project_manager`).

---

## Perubahan yang Dilakukan

### 1. Integrasi Library Visualisasi Premium
* Mengintegrasikan pustaka **ApexCharts** melalui CDN untuk menghadirkan visualisasi data yang responsif, interaktif (zoom, pan, dynamic series toggling), serta memiliki transisi animasi yang halus.

### 2. File yang Dimodifikasi
* **[index.php](file:///c:/laragon/www/newmega/modules/dashboard/index.php)**:
  * **Kueri Data Finansial**: Menambahkan tiga kueri SQL baru untuk mengumpulkan data:
    1. Arus Kas Bulanan (Uang Masuk vs Uang Keluar) dari 6 bulan terakhir.
    2. Performa Anggaran Proyek (Budget vs Biaya Aktual dari total PO dan klaim reimburse nota yang disetujui).
    3. Distribusi biaya pembelian berdasarkan Top 5 Kategori Barang.
  * **Layout Antarmuka (UI)**: Menambahkan kontainer grafik baru bermodel 2-kolom dengan transisi animasi CSS di bawah summary boxes utama.
  * **Inisialisasi Grafik**: Membuat script inisialisasi ApexCharts dengan warna bertema modern, pemformatan tooltip mata uang Rupiah (`Rp`), dan kontrol toggle visual.
  * **Widget Analitis**: Membuat widget ringkasan finansial yang menghitung proyeksi bersih (AR vs AP) serta efisiensi anggaran total proyek aktif secara dinamis.

### 3. Pengontrolan Hak Akses
* Memastikan grafik hanya dirender dan dimuat datanya jika pengguna masuk dengan role `super_admin`, `finance`, atau `project_manager`. Pengguna dengan role `karyawan` hanya akan melihat antarmuka Timesheet absensi sederhana mereka seperti biasa tanpa memuat ApexCharts atau query data grafik.

---

## Hasil Pengujian & Verifikasi

### 1. Verifikasi Sintaksis (PHP Lint)
* Berkas `modules/dashboard/index.php` telah divalidasi menggunakan linter PHP CLI dan bebas dari kesalahan sintaks:
  * `modules/dashboard/index.php` -> **No syntax errors detected**

### 2. Pengujian Fungsionalitas & Hak Akses (CLI Mock Test)
* Menggunakan skrip uji otomatis [test_dashboard.php](file:///c:/Users/ACER/.gemini/antigravity-ide/brain/0d57ed5b-5e0c-4736-8c57-bcf36409ed5e/scratch/test_dashboard.php):
  * **Sebagai Admin**: Halaman utama memuat dengan sukses, library ApexCharts diimpor, dan ketiga elemen kontainer grafik (`chart-cash-flow`, `chart-projects`, `chart-categories`) ditemukan dalam output HTML.
  * **Sebagai Karyawan**: Halaman memuat dengan sukses (output jauh lebih kecil), library ApexCharts **TIDAK** dimuat, dan kontainer grafik **TIDAK** dirender sama sekali untuk menjaga keamanan data.

---

## Panduan Pengujian Manual

1. Pastikan Anda berada di branch `dashboard`.
2. Buka aplikasi web di browser dan login menggunakan salah satu akun manajer/admin (misal role `super_admin` atau `finance`).
3. Pada halaman dashboard utama, Anda akan melihat visualisasi baru berupa:
   * **Area Chart (Green & Red)**: Tren Uang Masuk vs Uang Keluar.
   * **Donut Chart**: Distribusi Top 5 Kategori Terboros.
   * **Grouped Bar Chart**: Perbandingan Budget vs Biaya Aktual Proyek Aktif.
   * **Analytics Card**: Informasi Piutang vs Hutang Bersih, Efisiensi Proyek Terboros, dan progres pemakaian anggaran keseluruhan.
4. Coba arahkan kursor Anda ke area chart untuk melihat tooltip dinamis Rupiah. Anda juga dapat mengklik legenda "Uang Keluar" di bawah grafik arus kas untuk menyembunyikan garis merah dan membiarkan grafik menyesuaikan skalanya secara dinamis.
