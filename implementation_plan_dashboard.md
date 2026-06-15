# Rencana Implementasi: Dashboard Grafik Interaktif (Executive Dashboard)

Fitur **Executive Dashboard** dirancang untuk membantu para eksekutif dan manajer (super_admin, finance, project_manager) memantau metrik finansial, performa anggaran proyek, serta tren stok barang melalui visualisasi grafik yang interaktif, modern, dan bernilai estetika tinggi.

Kita akan menggunakan pustaka **ApexCharts** (melalui CDN) yang mendukung animasi halus, palet warna elegan, dan fungsionalitas interaktif penuh (tooltips, dynamic zooming, filter series).

---

## User Review Required

> [!IMPORTANT]
> **Pemuatan Pustaka Eksternal (ApexCharts)**:
> Kita akan memuat ApexCharts melalui CDN JSDelivr pada `modules/dashboard/index.php`. Pastikan server memiliki koneksi internet untuk memuat pustaka ini saat pengujian.
> 
> **Penyelarasan Data Pengeluaran Proyek**:
> Biaya Aktual Proyek dihitung dari kombinasi:
> 1. Total Nilai Purchase Order (PO) yang terhubung ke proyek tersebut (melalui MR links) dan memiliki status disetujui/selesai (`approved`, `partially_received`, `completed`).
> 2. Total Klaim Reimbursement Nota Karyawan (`nota_claims` status `paid`) yang item detailnya ditujukan untuk proyek tersebut.

---

## Proposed Changes

### 1. Struktur Database & Query Data Grafik
Tidak ada perubahan struktur tabel database. Kita akan menulis kueri SQL baru untuk menarik data statistik secara efisien:

#### A. Tren Arus Kas Bulanan (Uang Masuk vs Uang Keluar)
Mengonsolidasikan penerimaan dari customer (`customer_payments`) dan pengeluaran ke vendor/klaim (`vendor_payments` + `nota_claims` paid) dalam 6 bulan terakhir.
```sql
SELECT 
    months.month_label,
    COALESCE(SUM(cash_in.amount), 0) AS total_in,
    COALESCE(SUM(cash_out.amount), 0) AS total_out
FROM (
    SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m') AS month_label UNION ALL
    SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m') UNION ALL
    SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), '%Y-%m') UNION ALL
    SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '%Y-%m') UNION ALL
    SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m') UNION ALL
    SELECT DATE_FORMAT(CURDATE(), '%Y-%m')
) months
LEFT JOIN (
    SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month_label, amount 
    FROM customer_payments
) cash_in ON months.month_label = cash_in.month_label
LEFT JOIN (
    SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month_label, amount 
    FROM vendor_payments
    UNION ALL
    SELECT DATE_FORMAT(claim_date, '%Y-%m') AS month_label, total_amount AS amount
    FROM nota_claims WHERE status = 'paid'
) cash_out ON months.month_label = cash_out.month_label
GROUP BY months.month_label
ORDER BY months.month_label ASC
```

#### B. Budget vs Aktual Per Proyek
```sql
SELECT 
    p.id, 
    p.name, 
    p.budget,
    -- PO Expenses
    (SELECT COALESCE(SUM(po.total), 0) 
     FROM purchase_orders po 
     JOIN po_mr_links pml ON pml.po_id = po.id 
     JOIN material_requests mr ON pml.mr_id = mr.id 
     WHERE mr.project_id = p.id AND po.status NOT IN ('draft','cancelled','rejected')
    ) as total_po_value,
    -- Claim Nota Expenses
    (SELECT COALESCE(SUM(nci.amount), 0)
     FROM nota_claim_items nci
     JOIN nota_claims nc ON nci.claim_id = nc.id
     WHERE nci.project_id = p.id AND nc.status = 'paid'
    ) as total_claim_value
FROM projects p
WHERE p.status = 'active'
ORDER BY p.name ASC
```

#### C. Top 5 Kategori Material Paling Boros
```sql
SELECT 
    c.name AS category_name, 
    SUM(poi.total) AS total_spent
FROM purchase_order_items poi
JOIN items i ON poi.item_id = i.id
JOIN categories c ON i.category_id = c.id
JOIN purchase_orders po ON poi.po_id = po.id
WHERE po.status NOT IN ('draft','cancelled','rejected')
GROUP BY c.id
ORDER BY total_spent DESC
LIMIT 5
```

---

### 2. Modifikasi Frontend Dashboard

#### [MODIFY] [index.php](file:///c:/laragon/www/newmega/modules/dashboard/index.php)
* **Pemuatan Library**:
  * Menambahkan tag `<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>` ke bagian atas file atau di dalam blok JavaScript.
* **Markup HTML**:
  * Menambahkan section baru berdesain premium dengan pembungkus `<div class="row">` tepat di bawah Summary Boxes utama.
  * Membuat layout 2-kolom untuk grafis:
    * **Kolom Kiri (Atas)**: Grafik Arus Kas Arus Bulanan (Uang Masuk vs Uang Keluar) -> Tipe: *Area / Line Chart* dengan gradient fill.
    * **Kolom Kanan (Atas)**: Perbandingan Budget vs Aktual per Proyek -> Tipe: *Grouped Bar Chart* (Horizontal/Vertikal).
    * **Kolom Kiri (Bawah)**: 5 Kategori Material Paling Boros -> Tipe: *Donut / Radial Chart*.
    * **Kolom Kanan (Bawah)**: Ringkasan Analitis Keuangan (AP vs AR & Efisiensi Anggaran).
* **JavaScript Inisialisasi**:
  * Menambahkan skrip inisialisasi ApexCharts dengan konfigurasi warna premium (misalnya HSL harmonis, Navy, Emerald, Crimson, Amber).
  * Format mata uang Rupiah pada tooltip hover grafik agar mudah dibaca pengguna.

---

## Verification Plan

### Manual Verification
1. **Verifikasi Hak Akses**:
   * Login sebagai `karyawan` -> Pastikan dashboard grafik **TIDAK** muncul (karyawan hanya melihat timesheet absensi mereka).
   * Login sebagai `super_admin` / `finance` / `project_manager` -> Pastikan panel grafik interaktif muncul dengan animasi loading yang halus.
2. **Keakuratan Angka**:
   * Cocokkan nilai total pada grafik Arus Kas dengan laporan manual Buku Kas (Ledger).
   * Cocokkan grafik Budget vs Aktual Proyek dengan data di menu Proyek dan Laporan Pengeluaran Proyek.
3. **Fungsionalitas Interaktif**:
   * Arahkan kursor (*hover*) pada grafik batang/garis, pastikan tooltip memformat angka dalam format Rupiah (contoh: `Rp 15.000.000`).
   * Klik legenda grafik (misalnya mematikan series "Budget" pada perbandingan Proyek) untuk memastikan visualisasi grafik beradaptasi secara dinamis.
4. **Keamanan & Respon Server**:
   * Pastikan tidak ada kegagalan pemuatan halaman (Error 500) dan loading chart berjalan cepat.
