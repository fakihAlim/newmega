# Acuan Standar Format Laporan (Screen & Print)

Dokumen ini berfungsi sebagai panduan dan template acuan untuk menstandarkan format seluruh laporan di sistem **Newmega** (seperti Invoice, Quotation, Purchase Order, dan Material Request), baik saat ditampilkan di layar maupun saat dicetak ke PDF/Kertas A4.

---

## 1. Spesifikasi Style Laporan

### A. Tipografi & Warna
- **Warna Font**: **Pure Black (`#000000`)** untuk seluruh teks laporan guna menjamin kontras tinggi.
- **Font Family**: `'Inter', -apple-system, sans-serif` (Screen) dan `Arial, sans-serif` (Print).
- **Skala Font**:
  - Judul Laporan Utama (misal: "INVOICE"): `48px` (Tebal, rata tengah)
  - Nama Perusahaan Pengirim (From) & Pelanggan (To): `20px` (Tebal)
  - Teks Detail Konten (Alamat, telp, dll): `12px` (Regular, line-height: 1.5)
  - Default Laporan / Teks Tabel (Header & Isi): `13px` (Tebal untuk Header, Regular untuk Isi)
  - Teks Catatan / Terms: `12px` (Regular)

### B. Spesifikasi Tabel Utama
- **Ukuran Font Sel**: `13px !important` untuk header dan isi detail baris.
- **Padding Sel**: Atas/bawah: `5px`, Kiri/kanan: `10px` (`padding: 5px 10px !important`).
- **Warna Header**: Latar belakang abu-abu netral `#f1f5f9` (harus diset agar tercetak).
- **Warna Garis (Borders)**: Abu-abu sedang `#cbd5e1` di layar, dan hitam solid `#000000` saat dicetak.

### C. Kolom Subtotal & Total (Summary)
- **Tanpa Warna Latar**: Baris Total tidak boleh menggunakan fill color (harus putih polos/transparan).
- **Perataan Teks**: Label diatur **rata kiri** (`text-align: left`).
- **Borders**: Dikelilingi garis pembatas tipis.

---

## 2. Struktur Kode HTML Standar

Berikut adalah contoh markup HTML yang bisa di-copy-paste untuk membuat laporan baru:

```html
<!-- Kontainer Laporan -->
<div class="card-body printable-area p-4 bg-white">
    
    <!-- 1. Header Laporan (Judul di Tengah, Meta di Kanan) -->
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div style="flex: 1;"></div>
        <div style="flex: 1; text-align: center;">
            <h1 class="font-weight-bold m-0" style="font-size: 48px; letter-spacing: 1px;">JUDUL LAPORAN</h1>
        </div>
        <div style="flex: 1; text-align: right;">
            <table class="table-sm table-borderless font-weight-bold" style="font-size: 15px; margin-left: auto;">
                <tr>
                    <td class="text-left pr-2 pb-0">Nomor Dokumen</td>
                    <td class="text-left pb-0">: NOMOR_DOKUMEN</td>
                </tr>
                <tr>
                    <td class="text-left pr-2 pt-0">Tanggal</td>
                    <td class="text-left pt-0">: TANGGAL_DOKUMEN</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- 2. Informasi Pengirim & Penerima (Flexbox Grid) -->
    <div class="row no-gutters mb-4" style="gap: 20px; display: flex;">
        <!-- Pengirim (From) -->
        <div class="report-info-col col p-3" style="border: 1px solid #e2e8f0; border-radius: 6px; background-color: #f8fafc; flex: 1;">
            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; margin-bottom: 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">From</div>
            <h4 class="font-weight-bold mb-1" style="font-size: 20px;">NAMA_PERUSAHAAN_PENGIRIM</h4>
            <div style="font-size: 12px; line-height: 1.5; color: #334155;">
                ALAMAT_LENGKAP<br>
                Email: EMAIL_PENGIRIM | Phone: TELP_PENGIRIM
            </div>
        </div>
        <!-- Penerima (To) -->
        <div class="report-info-col col p-3" style="border: 1px solid #e2e8f0; border-radius: 6px; background-color: #f8fafc; flex: 1;">
            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; margin-bottom: 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">To</div>
            <h4 class="font-weight-bold mb-1" style="font-size: 20px;">NAMA_PELANGGAN_ATAU_VENDOR</h4>
            <div style="font-size: 12px; line-height: 1.5; color: #334155;">
                ALAMAT_TUJUAN<br>
                Phone: TELP_PENERIMA
            </div>
        </div>
    </div>

    <!-- 3. Tabel Detail Item -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-sm report-table mb-0" style="width: 100%;">
            <thead>
                <tr class="text-center font-weight-bold">
                    <th style="width: 5%;">No</th>
                    <th>Deskripsi</th>
                    <th style="width: 10%;">Qty</th>
                    <th style="width: 10%;">Satuan</th>
                    <th style="width: 15%;">Harga Satuan</th>
                    <th style="width: 20%;">Total Harga</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">1</td>
                    <td>Nama barang atau detail jasa</td>
                    <td class="text-right">10</td>
                    <td class="text-center">Pcs</td>
                    <td class="text-right">100.000</td>
                    <td class="text-right">1.000.000</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 4. Catatan (Terms) & Ringkasan Biaya (Summary) -->
    <div class="row no-gutters mb-4" style="gap: 20px; display: flex;">
        <!-- Catatan / Terms -->
        <div class="col pr-0 d-flex flex-column" style="flex: 7;">
            <div class="p-2 px-3 font-weight-bold report-terms-header">
                Term and Conditions :
            </div>
            <div class="p-3 flex-grow-1 report-terms-body">
                Detail instruksi pembayaran, rekening, bank, dll.
            </div>
        </div>
        <!-- Summary -->
        <div class="col pl-0" style="flex: 5;">
            <table class="table-sm table-bordered report-summary-table w-100 h-100">
                <tr>
                    <td class="report-summary-label">Subtotal</td>
                    <td class="report-summary-value">1.000.000</td>
                </tr>
                <tr>
                    <td class="report-summary-label">Tax (11%)</td>
                    <td class="report-summary-value">110.000</td>
                </tr>
                <tr>
                    <td class="report-summary-total-label">Total</td>
                    <td class="report-summary-total-value">1.110.000</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- 5. Bagian Tanda Tangan (Signature) -->
    <div class="d-flex justify-content-end mt-4 pt-2" style="font-size: 12px;">
        <div style="width: 250px; text-align: center; padding: 10px;">
            <p class="mb-5 font-weight-bold text-uppercase">Hormat Kami,</p>
            <div style="height: 60px;"></div>
            <strong class="text-uppercase" style="text-decoration: underline;">
                NAMA_PANDATANGAN
            </strong><br>
            <span style="font-size: 11px;">Authorized Signature</span>
        </div>
    </div>

</div>
```

---

## 3. Gaya CSS Standar (Letakkan di Bagian Bawah File PHP)

```html
<style>
/* --- Gaya Laporan di Layar --- */
.printable-area {
    color: #000000 !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif !important;
    font-size: 13px;
}

/* Memaksa semua teks di area laporan berwarna hitam */
.printable-area * {
    color: #000000 !important;
}

.report-table {
    width: 100% !important;
    border-collapse: collapse !important;
}

/* Font size 13px & padding baris 5px */
.report-table th, .report-table td {
    border: 1px solid #cbd5e1 !important;
    padding: 5px 10px !important;
    vertical-align: middle !important;
    font-size: 13px !important;
}

.report-table thead th {
    background-color: #f1f5f9 !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}

.report-terms-header {
    background-color: #f1f5f9 !important;
    border: 1px solid #cbd5e1;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-top-left-radius: 6px;
    border-top-right-radius: 6px;
}

.report-terms-body {
    border: 1px solid #cbd5e1;
    border-top: none;
    font-size: 12px;
    border-bottom-left-radius: 6px;
    border-bottom-right-radius: 6px;
}

.report-summary-table {
    border-collapse: collapse !important;
    font-size: 13px !important;
    border: 1px solid #cbd5e1 !important;
}

.report-summary-table td {
    padding: 6px 12px !important;
    border: 1px solid #cbd5e1 !important;
}

.report-summary-label {
    text-align: left !important;
    font-weight: 600;
    background-color: transparent !important;
}

.report-summary-value {
    text-align: right !important;
    font-weight: 600;
}

.report-summary-total-label {
    text-align: left !important;
    font-weight: 800;
    font-size: 14px;
    background-color: transparent !important;
}

.report-summary-total-value {
    text-align: right !important;
    font-weight: 800;
    font-size: 15px;
}

/* --- Gaya Khusus Saat Cetak --- */
@media print {
    @page {
        size: A4 portrait;
        margin: 15mm;
    }
    body { 
        background-color: white !important; 
    }
    /* Sembunyikan elemen non-cetak */
    .main-sidebar, .main-header, .d-print-none, .card-footer, .breadcrumb, .content-header { 
        display: none !important; 
    }
    .content-wrapper { 
        margin-left: 0 !important; 
        padding: 0 !important; 
        background: none !important;
    }
    .card { 
        border: none !important; 
        box-shadow: none !important; 
    }
    .card-header { 
        display: none !important; 
    }
    .printable-area { 
        width: 100% !important; 
        margin: 0 !important; 
        padding: 0 !important; 
    }
    /* Memaksa border berwarna hitam solid saat diprint */
    .report-table th, .report-table td,
    .report-terms-header, .report-terms-body,
    .report-summary-table, .report-summary-table td,
    .report-info-col {
        border: 1px solid #000000 !important;
    }
    .report-table th, .report-table td {
        font-size: 13px !important;
    }
    /* Memaksa warna background abu-abu tercetak */
    .report-table thead th,
    .report-terms-header {
        background-color: #f1f5f9 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .report-summary-table td {
        background-color: transparent !important;
    }
}
</style>
```
