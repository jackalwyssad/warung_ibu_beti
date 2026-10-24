# KONTRAK EVENT ASINKRON (MESSAGE BROKER - RABBITMQ)
**Proyek Akhir Integrasi Sistem dan Komputasi Awan (25IF1504)**  
**Mitra: Warung Sembako Ibu Beti**  
**Kelompok: Intel (STMIK Bandung - Kurikulum 2025)**

Dokumen ini mendefinisikan spesifikasi kontrak pesan asinkron (*Domain Events*) yang dipertukarkan antar-layanan menggunakan message broker RabbitMQ dengan pola arsitektur **Publish/Subscribe** dan **Transactional Outbox Pattern**.

---

## 1. Konvensi Penamaan & Standar Kontrak
1. **Nama Event:** Menggunakan kata kerja bentuk lampau (*past-tense event naming*) untuk merepresentasikan fakta yang telah terjadi di dalam domain bisnis (contoh: `PesananWhatsAppMasuk`, `BarangDiterimaSupplier`).
2. **Format Payload:** JSON Object (`application/json`) dengan amplop standar (*Event Envelope*): `event_id`, `event_type`, `occurred_at`, dan `payload`.
3. **Idempotensi:** Setiap consumer wajib mencatat `event_id` ke tabel deduplikasi (`processed_events`) untuk menjamin semantik *at-least-once delivery*.

---

## 2. Ringkasan Topologi RabbitMQ

| Komponen | Nama di RabbitMQ | Tipe / Driver | Keterangan |
| :--- | :--- | :--- | :--- |
| **Exchange** | `warung.pengadaan.events` | `topic` / `direct` | Pusat distribusi domain event warung |
| **Dead Letter Exchange (DLX)** | `warung.pengadaan.dlx` | `direct` | Menampung pesan gagal olah / poison message |
| **Worker Queue (WA)** | `wa.pesanan.masuk.queue` | `quorum` / standard | Antrean konsumsi pesanan daring |
| **Worker Queue (Pembukuan)** | `pembukuan.jurnal.queue` | `quorum` / standard | Antrean pencatatan jurnal kas & stok |

---

## 3. Spesifikasi Kontrak Event

### Event 1: `PesananWhatsAppMasuk` (Jalur Asinkron Berjalan di Demo)
*Diterbitkan saat pelanggan memesan sembako via WhatsApp gateway/API.*

- **Nama Event:** `PesananWhatsAppMasuk`
- **Exchange:** `warung.pengadaan.events`
- **Routing Key:** `wa.pesanan.masuk`
- **Penerbit (Publisher):** Layanan Saluran Daring (WhatsApp Gateway)
- **Penerima (Consumer):** Layanan Pengadaan & Inventori Gudang (`wa:proses-pesanan`)
- **Tujuan Bisnis:** Memvalidasi ketersediaan sembako di gudang, mereservasi stok secara asinkron tanpa memblokir chat pelanggan, dan menyiapkan antrean kurir.

#### Skema Payload:
```json
{
  "event_id": "string (UUIDv4) - Pengidentifikasi unik pesan",
  "event_type": "string - Nilai tetap: PesananWhatsAppMasuk",
  "occurred_at": "string (ISO 8601) - Waktu penerbitan event, misal: 2026-10-24T14:30:00Z",
  "payload": {
    "pesanan_id": "string (UUIDv4) - ID pesanan di database",
    "nomor_telepon": "string (E.164) - Nomor WhatsApp pemesan, misal: 08123456789",
    "nama_pelanggan": "string - Nama pemesan",
    "alamat_pengiriman": "string - Alamat tujuan antar sembako",
    "items": [
      {
        "barang_id": "integer - ID barang di master katalog",
        "kode_barang": "string - SKU barang, misal: BRG-001",
        "nama_barang": "string - Nama sembako, misal: Beras Pandan Wangi 5kg",
        "kuantitas": "integer (>= 1) - Jumlah unit yang dipesan",
        "harga_satuan": "number (float/decimal) - Harga per unit dalam Rupiah",
        "subtotal": "number (float/decimal) - Total harga item"
      }
    ],
    "total_pembayaran": "number (float/decimal) - Akumulasi harga keseluruhan",
    "metode_pembayaran": "string (enum: transfer_bank, qris, cod)",
    "status": "string (enum: menunggu_konfirmasi, disetujui, ditolak)"
  }
}
```

---

### Event 2: `BarangDiterimaSupplier`
*Diterbitkan saat pesanan pembelian (Purchase Order) tiba dari supplier dan diverifikasi di gudang.*

- **Nama Event:** `BarangDiterimaSupplier`
- **Exchange:** `warung.pengadaan.events`
- **Routing Key:** `pengadaan.barang.diterima`
- **Penerbit (Publisher):** Layanan Pengadaan & Inventori Gudang
- **Penerima (Consumer):** Layanan Kasir (POS) & Layanan Pembukuan / Keuangan
- **Tujuan Bisnis:** Sinkronisasi penambahan stok fisik secara realtime dan pencatatan timbulnya hutang dagang / pengeluaran kas di buku besar.

#### Skema Payload:
```json
{
  "event_id": "string (UUIDv4)",
  "event_type": "string - Nilai tetap: BarangDiterimaSupplier",
  "occurred_at": "string (ISO 8601)",
  "payload": {
    "pengadaan_id": "string (UUIDv4) - ID referensi penerimaan",
    "nomor_po": "string - Nomor PO pengadaan, misal: PO-20261024-001",
    "supplier_id": "integer - ID pemasok distributor",
    "nama_supplier": "string - Nama PT / Agen Distributor",
    "tanggal_penerimaan": "string (date: YYYY-MM-DD)",
    "items_diterima": [
      {
        "barang_id": "integer",
        "kode_barang": "string",
        "kuantitas_diterima": "integer (>= 1)",
        "harga_beli_satuan": "number",
        "total_harga_beli": "number"
      }
    ],
    "total_nilai_barang": "number",
    "status_pembayaran_supplier": "string (enum: lunas, tempo_30_hari)"
  }
}
```

---

### Event 3: `PesananPembelianDikonfirmasi`
*Diterbitkan saat pengelola Warung Ibu Beti menyetujui draft Purchase Order pengadaan barang stok kritis.*

- **Nama Event:** `PesananPembelianDikonfirmasi`
- **Exchange:** `warung.pengadaan.events`
- **Routing Key:** `pengadaan.po.dikonfirmasi`
- **Penerbit (Publisher):** Layanan Pengadaan
- **Penerima (Consumer):** Layanan Integrasi Supplier (B2B EDI / Email / WA Supplier)
- **Tujuan Bisnis:** Mengirimkan Purchase Order elektronik ke pihak distributor sembako resmi secara otomatis tanpa panggilan telepon manual.

---

### Event 4: `TransaksiKasirSelesai`
*Diterbitkan setiap kali transaksi kasir tatap muka (POS) selesai dicatat.*

- **Nama Event:** `TransaksiKasirSelesai`
- **Exchange:** `warung.pengadaan.events`
- **Routing Key:** `kasir.transaksi.selesai`
- **Penerbit (Publisher):** Layanan Kasir & POS
- **Penerima (Consumer):** Layanan Pembukuan & Pelaporan Laba-Rugi
- **Tujuan Bisnis:** Mengalirkan data omzet harian dan pergerakan persediaan HPP ke laporan keuangan secara terpisah (*decoupled ledger*).

---

## 4. Mekanisme Penanganan Kegagalan (Dead Letter Exchange)
1. Jika consumer gagal memproses pesan (misal: format JSON tidak valid, database connection timeout), pesan akan di-*reject* dengan `requeue: false`.
2. Broker RabbitMQ secara otomatis memindahkan pesan ke Dead Letter Exchange `warung.pengadaan.dlx` menuju antrean `warung.dlq.poison-messages`.
3. Administrator/Operator dapat memeriksa payload penyebab error pada antrean DLQ dan melakukan *replay* pesan setelah perbaikan kode dilakukan.
