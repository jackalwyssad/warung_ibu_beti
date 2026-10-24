# Sistem Terintegrasi Warung Ibu Beti

**Mata Kuliah:** Integrasi Sistem dan Komputasi Awan (25IF1504)  
**Institusi:** STMIK Bandung  
**Kelompok:** Intel  

### Anggota Kelompok & Pembagian Tugas:
1. **Jacky Firmansah** (1224010)
2. **Arya Kusuma Dinata** (1224005)
3. **Mochamad Aldi Ardiansyah** (1224020)
4. **Andhi Yudha Permana** (1224017)

---

## Berkas Deliverable Checkpoint 1 (Proyek Akhir 25IF1504)

Seluruh berkas persyaratan Checkpoint 1 telah lengkap dan siap diperiksa:
1. **Dokumen Analisis (PDF, 6 Halaman):** `Dokumen_Analisis_Checkpoint_1_Warung_Ibu_Beti.pdf` *(diserahkan terpisah via LMS / Dosen)*  
   *Memuat konteks mitra, persoalan data silo, evaluasi trade-off 3 gaya integrasi, diagram 4 batas layanan, dan alur pemeriksaan 4 menit.*
2. **Kontrak API (OpenAPI 3.1 & Problem Details RFC 9457):** [`openapi.yaml`](./openapi.yaml)  
   *Design-first, bersih dari error linter Stoplight Spectral (0 errors), skema data lengkap.*
3. **Kontrak Event Asinkron RabbitMQ:** [`KONTRAK_EVENT.md`](./KONTRAK_EVENT.md)  
   *Event bentuk lampau (`PesananWhatsAppMasuk`, `BarangDiterimaSupplier`, `TransaksiKasirSelesai`), exchange `warung.pengadaan.events`, routing key, skema JSON, publisher, dan consumer.*
4. **Slide Presentasi (16:9 Widescreen):** `Presentasi_Checkpoint_1_Warung_Ibu_Beti.pptx` *(diserahkan terpisah via LMS / Dosen)*  
   *Dirancang khusus untuk presentasi 4 menit sesuai rubrik penilaian (Menit 0-1 Arsitektur, Menit 1-3 Demo, Menit 3-4 Q&A).*

---

## 1. Latar Belakang & Ringkasan Proyek

Proyek ini dibangun sebagai implementasi nyata dari **Lembar Kerja Tugas 3: Memilih Gaya Integrasi** untuk studi kasus **Warung Ibu Beti** (bisnis kelontong & sembako modern). Sistem ini mengintegrasikan seluruh operasional warung mulai dari transaksi kasir belanja langsung di toko fisik, pemesanan online via Bot WhatsApp, pembayaran digital (QRIS & Tunai), hingga pengelolaan rantai pasok / kulakan barang dari supplier.

Sesuai instruksi dan materi perkuliahan, proyek ini memadukan dua gaya integrasi utama:
1. **Shared Database:** Diterapkan pada **Sistem Kasir (POS) ↔ Sistem Stok/Inventori**, karena beroperasi pada lokasi dan waktu yang sama di toko fisik serta membutuhkan pemotongan stok *real-time* dengan transaksi ACID atomik.
2. **Messaging (Asynchronous via RabbitMQ):** Diterapkan pada **Bot Pemesanan WhatsApp ↔ Sistem Stok/Inventori**, karena kanal obrolan bersifat asinkron dan membutuhkan *loose coupling* agar penambahan kanal baru (misal aplikasi web) tidak mengubah sistem stok utama.
3. **Remote Procedure Invocation (REST API Level 2):** Seluruh layanan mengekspos endpoint API terstandarisasi berbasis spesifikasi OpenAPI 3.1.0 dengan format galat **RFC 9457 Problem Details** (`application/problem+json`).

---

## 2. Diagram Arsitektur Sistem

Arsitektur sistem memisahkan tanggung jawab transaksi belanja langsung, antrean pesan online, dan logistik pengadaan:

```mermaid
flowchart TB
    %% Kanal Pengguna
    subgraph Klien ["1. Kanal Pengguna & Kasir"]
        PelangganToko["Pelanggan Toko Fisik"]
        PelangganWA["Pelanggan Online WhatsApp"]
        Kasir["Petugas Kasir Toko"]
        SupplierPemasok["Pemasok / Distributor Sembako"]
    end

    %% Layanan Aplikasi Laravel (Port 8090)
    subgraph CoreService ["2. Layanan Inti Laravel API (Port 8090)"]
        direction TB
        APIPos["Modul Kasir POS"]
        APIWa["Modul Webhook Bot WhatsApp"]
        APIStok["Modul Inventori Sembako"]
        APIPengadaan["Modul Pengadaan & Supplier"]
        OutboxWorker["Outbox Publisher Worker<br/>(pengadaan:kirim-outbox)"]
        WAWorker["Worker Konsumen WhatsApp<br/>(wa:proses-pesanan)"]
    end

    %% Penyimpanan Shared Database (Port 3309)
    subgraph DataStore ["3. Shared Database MySQL (Port 3309)"]
        direction TB
        TBarang[("Tabel barang<br/>(Stok Real-time Sembako)")]
        TKasir[("Tabel transaksi_kasir & pembayaran<br/>(Tunai & QRIS)")]
        TWa[("Tabel pesanan_wa & item_pesanan_wa")]
        TOutbox[("Tabel outbox_kirim<br/>(Event Atomik ACID)")]
        TPo[("Tabel supplier, PO,<br/>penerimaan, hutang")]
    end

    %% Message Broker RabbitMQ (Port 5676 & 15676)
    subgraph Broker ["4. Message Broker RabbitMQ (Port 5676 & 15676)"]
        direction TB
        TopicEx[["Topic Exchange:<br/>warung.pengadaan.events"]]
        QWA[("Antrean: wa.pesanan-antrean")]
        QGudang[("Antrean: gudang.masuk")]
        QHutang[("Antrean: keuangan.buku-hutang")]
        ExDLX[["Fanout DLX: warung.pengadaan.dlx"]]
        QDLQ[("Antrean DLQ: pengadaan.audit.dlq")]
    end

    %% Relasi Alur Kasir (Shared DB)
    PelangganToko -->|Belanja Langsung| Kasir
    Kasir -->|1. Input Transaksi| APIPos
    APIPos -->|Gaya: SHARED DATABASE<br/>Pessimistic Row Lock & Decrement| TBarang
    APIPos -->|Simpan Transaksi & Pembayaran| TKasir

    %% Relasi Alur WhatsApp (Messaging)
    PelangganWA -->|2. Kirim Chat Order| APIWa
    APIWa -->|Simpan Draft Pesanan| TWa
    APIWa -->|Gaya: MESSAGING<br/>Simpan Event ke Outbox| TOutbox
    TOutbox -.->|Polling Pending Events| OutboxWorker
    OutboxWorker -->|Publish Event wa.pesanan.masuk| TopicEx
    TopicEx -->|Binding: wa.pesanan.*| QWA
    QWA -->|Konsumsi Pesan Asinkron| WAWorker
    WAWorker -->|Reservasi Stok & Update Status| TBarang

    %% Relasi Alur Pengadaan
    SupplierPemasok -->|3. Kirim Pasokan Sembako| APIPengadaan
    APIPengadaan --> TPo
    APIPengadaan -->|Penerimaan Fisik Tambah Stok| TBarang
    APIPengadaan -->|Event PO & Penerimaan| TOutbox

    TopicEx -->|Binding: pengadaan.po.*<br/>pengadaan.barang.*| QGudang
    TopicEx -->|Binding: pengadaan.po.dikonfirmasi<br/>pengadaan.hutang.*| QHutang
    QWA -.->|Pesan Error/Nack| ExDLX
    ExDLX --> QDLQ
```

---

## 3. Rincian Alur Kerja Sistem (Flows)

Sistem mengimplementasikan tiga alur bisnis utama yang saling terhubung:

### Alur A: Transaksi Kasir POS (Gaya: Shared Database)
Alur ini melayani pelanggan yang datang langsung berbelanja ke warung fisik:

```mermaid
sequenceDiagram
    autonumber
    actor Pelanggan as Pelanggan Toko
    actor Kasir as Petugas Kasir
    participant API as Modul Kasir API
    participant DB as Shared Database (MySQL)

    Pelanggan->>Kasir: Membawa barang belanjaan (misal: Beras & Minyak)
    Kasir->>API: POST /api/v1/kasir/transaksi (SKU barang & Metode Bayar)
    activate API
    API->>DB: BEGIN TRANSACTION (ACID)
    API->>DB: SELECT * FROM barang WHERE sku = '...' FOR UPDATE (Lock Row)
    alt Stok Cukup
        API->>DB: UPDATE barang SET stok = stok - qty (Potong Stok Instan)
        API->>DB: INSERT INTO transaksi_kasir & item_transaksi_kasir
        API->>DB: INSERT INTO pembayaran (Tunai / QRIS)
        API->>DB: COMMIT TRANSACTION
        API-->>Kasir: HTTP 201 Created (Struk & Status Lunas)
        Kasir-->>Pelanggan: Serahkan barang & kembalian uang / bukti QRIS
    else Stok Kurang
        API->>DB: ROLLBACK TRANSACTION
        API-->>Kasir: HTTP 422 Unprocessable Entity (RFC 9457: Stok Tidak Mencukupi)
        Kasir-->>Pelanggan: Informasikan bahwa stok fisik habis
    end
    deactivate API
```

**Karakteristik & Trade-off (Sesuai Analisis Tugas 3):**
- **Application Coupling:** *Tight coupling*. Kasir dan stok berada di toko fisik yang sama.
- **Data Timeliness:** *Real-time*. Pemotongan stok terjadi detik itu juga tanpa menunggu antrean batch.
- **Synchronous vs Asynchronous:** *Synchronous*. Kasir menunggu kepastian ketersediaan stok sebelum struk dicetak.
- **Integrator Intrusiveness:** Sedang. Dirancang sebagai satu kesatuan sistem internal toko.

---

### Alur B: Pemesanan Online via Bot WhatsApp (Gaya: Messaging via RabbitMQ)
Alur ini melayani pelanggan yang memesan sembako dari rumah via chat WhatsApp:

```mermaid
sequenceDiagram
    autonumber
    actor Pelanggan as Pelanggan Online
    participant WA as Bot WhatsApp / Webhook
    participant Outbox as Tabel outbox_kirim
    participant Worker as Outbox Publisher Worker
    participant RMQ as RabbitMQ (Topic Exchange)
    participant Consumer as Worker Konsumen WA
    participant DB as Shared Database (Stok)

    Pelanggan->>WA: Kirim pesan pesanan: "Pesan Beras 5kg 1 karung"
    WA->>Outbox: Simpan pesanan draft & Event PesananWhatsAppMasuk (ACID Outbox)
    WA-->>Pelanggan: HTTP 202 Accepted ("Pesanan Anda sedang diantrekan...")
    
    Worker->>Outbox: Baca event berstatus pending
    Worker->>RMQ: Publish pesan ke exchange 'warung.pengadaan.events' (Key: 'wa.pesanan.masuk')
    Worker->>Outbox: Update status event -> 'terkirim'
    
    RMQ->>Consumer: Teruskan pesan ke antrean 'wa.pesanan-antrean'
    activate Consumer
    Consumer->>DB: Cek ketersediaan stok barang
    alt Stok Tersedia
        Consumer->>DB: Potong stok & ubah status pesanan -> 'diproses'
        Consumer-->>Pelanggan: Kirim notifikasi WA: "Pesanan dikonfirmasi & disiapkan!"
    else Stok Kosong
        Consumer->>DB: Ubah status pesanan -> 'dibatalkan'
        Consumer-->>Pelanggan: Kirim notifikasi WA: "Maaf, stok barang sedang habis."
    end
    Consumer->>RMQ: basic_ack (Selesai diproses)
    deactivate Consumer
```

**Karakteristik & Trade-off (Sesuai Analisis Tugas 3):**
- **Application Coupling:** *Loose coupling*. Provider API WhatsApp / Webhook dapat diganti kapan saja tanpa memengaruhi sistem stok.
- **Data Timeliness:** *Near real-time*. Pesanan diantrekan secara asinkron.
- **Synchronous vs Asynchronous:** *Asynchronous*. Pelanggan segera mendapat konfirmasi antrean, sementara reservasi stok berjalan di latar belakang.
- **Integrator Intrusiveness:** Rendah. Integrasi cukup melalui webhook dan message broker.

---

### Alur C: Logistik Pengadaan & Supplier (Rantai Pasok Hulu ke Hilir)
Alur ini melengkapi ekosistem warung agar barang yang habis dapat di-restock dari supplier:

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Admin Warung
    actor Supplier as Supplier / Pemasok
    participant API as Modul Pengadaan API
    participant DB as Database (PO & Hutang)
    participant Stok as Tabel barang

    Admin->>API: POST /api/v1/pesanan (Buat PO Kulakan Sembako)
    API->>DB: Simpan status Draft
    Admin->>API: POST /api/v1/pesanan/{id}/konfirmasi (Validasi Plafon Kredit Supplier)
    alt Nilai PO <= Sisa Plafon Kredit
        API->>DB: Ubah status PO -> 'dikonfirmasi' & tulis event outbox
        Admin->>Supplier: Kirim Surat Purchase Order (PO)
        Supplier->>Admin: Kirim pasokan fisik barang ke warung
        Admin->>API: POST /api/v1/pesanan/{id}/terima-barang (Catat Kedatangan Fisik)
        API->>Stok: Akumulasi penambahan stok barang di toko (Stok Bertambah)
        API->>DB: Terbitkan Faktur Hutang Dagang Tempo (Invoice)
    else Nilai PO Melebihi Plafon Kredit
        API-->>Admin: HTTP 422 (KreditSupplierTerlampaui - RFC 9457)
    end
```

---

## 4. Pemetaan Port Layanan

Port pada proyek ini telah dialokasikan secara independen agar seluruh modul berjalan dengan stabil:

| Layanan / Subsistem | Port Proyek (`warung_ibu_beti`) | Keterangan |
|---|---|---|
| **Aplikasi Laravel API** | **`8090`** | Base URL: `http://localhost:8090/api/v1` |
| **MySQL Database** | **`3309`** | DB: `warung_beti`, User: `sail`, Pass: `password` |
| **RabbitMQ AMQP Broker** | **`5676`** | Host broker untuk koneksi aplikasi |
| **RabbitMQ Web Dashboard** | **`15676`** | URL: `http://localhost:15676` (`beti` / `rahasia123`) |
| **Swagger UI (Dokumentasi API)**| **`8083`** | URL: `http://localhost:8083` |
| **Vite Dev Server** | **`5176`** | Frontend asset bundler |

---

## 5. Panduan Menjalankan Sistem

### Prasyarat
- Docker & Docker Compose
- WSL 2 (Ubuntu) bagi pengguna Windows

### Langkah 1: Menjalankan Kontainer Docker Sail
```bash
cd ~/warung_ibu_beti
./vendor/bin/sail up -d
```

### Langkah 2: Menjalankan Migrasi Database
```bash
./vendor/bin/sail artisan migrate
```

### Langkah 3: Menjalankan Uji Skenario Integrasi Tugas 3 (Otomatis)
Perintah ini mendemonstrasikan seluruh alur Tugas 3 secara otomatis:
```bash
./vendor/bin/sail artisan warung:uji-tugas3
```

**Hasil Uji Skenario Tugas 3:**
- [x] Inisialisasi stok beras & minyak goreng di inventori toko.
- [x] Transaksi kasir langsung memotong stok via **Shared Database** secara ACID.
- [x] Transaksi kasir digital dengan metode pembayaran QRIS.
- [x] Pesanan online pelanggan via Bot WhatsApp diterbitkan ke antrean **RabbitMQ (Messaging)**.
- [x] Worker konsumen WhatsApp (`wa:proses-pesanan`) mereservasi stok secara asinkron.
- [x] Proteksi galat standar RFC 9457 jika stok toko tidak mencukupi.

### Langkah 4: Menjalankan Automated Test Suite (PHPUnit)
```bash
./vendor/bin/sail artisan test
```
*Hasil: 12 feature & unit test lulus 100% (38 assertions).*

### Langkah 5: Menjalankan Worker Pesanan WhatsApp
Untuk memproses antrean pesan WhatsApp yang masuk secara berkala:
```bash
./vendor/bin/sail artisan wa:proses-pesanan
```

---

## 6. Dokumentasi Endpoint REST API & Contoh cURL

Dokumentasi interaktif dapat diakses melalui **Swagger UI** di: `http://localhost:8083`.

### A. Health Check
```bash
curl -X GET http://localhost:8090/api/v1/health
```

### B. Katalog Stok Sembako (Inventori)
```bash
# Menambah Barang Baru
curl -X POST http://localhost:8090/api/v1/barang \
  -H "Content-Type: application/json" \
  -d '{
    "sku": "TELUR-1KG",
    "nama_barang": "Telur Ayam Ras 1kg",
    "kategori": "sembako",
    "stok": 50,
    "harga_beli": 24000,
    "harga_jual": 28000,
    "satuan": "kg"
  }'

# Melihat Daftar Stok Barang
curl -X GET http://localhost:8090/api/v1/barang
```

### C. Kasir POS (Gaya: Shared Database)
```bash
# Transaksi Belanja Langsung di Kasir (Potong Stok Real-Time)
curl -X POST http://localhost:8090/api/v1/kasir/transaksi \
  -H "Content-Type: application/json" \
  -d '{
    "kasir_nama": "Mbak Beti",
    "metode_bayar": "tunai",
    "uang_diterima": 100000,
    "items": [
      {"sku": "TELUR-1KG", "qty": 2}
    ]
  }'
```

### D. Bot WhatsApp (Gaya: Messaging via RabbitMQ)
```bash
# Pelanggan Memesan via Chat WhatsApp
curl -X POST http://localhost:8090/api/v1/wa/pesanan \
  -H "Content-Type: application/json" \
  -d '{
    "nomor_telepon": "081298765432",
    "nama_pelanggan": "Ibu Siti",
    "alamat_antar": "Jl. Mawar No. 14",
    "metode_bayar": "tunai",
    "items": [
      {"sku": "TELUR-1KG", "qty": 1}
    ]
  }'

# Mengirim Pesan Tertunda ke RabbitMQ
./vendor/bin/sail artisan pengadaan:kirim-outbox

# Memproses Antrean Reservasi Stok
./vendor/bin/sail artisan wa:proses-pesanan
```

### E. Pengadaan Supplier (Restock Barang Masuk)
```bash
# Mendaftarkan Supplier
curl -X POST http://localhost:8090/api/v1/suppliers \
  -H "Content-Type: application/json" \
  -d '{
    "kode_supplier": "SUP-001",
    "nama": "PT Sumber Sembako Utama",
    "kontak": "081234567890",
    "limit_kredit": 20000000
  }'

# Penerimaan Barang Kulakan (Otomatis Menambah Stok Toko)
curl -X POST http://localhost:8090/api/v1/pesanan/1/terima-barang \
  -H "Content-Type: application/json" \
  -d '{
    "penerima": "Mang Udin (Gudang)",
    "catatan": "Barang utuh",
    "items": [
      {"sku": "TELUR-1KG", "jumlah_diterima": 50}
    ]
  }'
```

---

## 7. Kesimpulan Integrasi

Proyek ini telah membuktikan integrasi sistem komputasi awan yang modular dan andal:
1. **Shared Database** memberikan performa maksimal dan konsistensi instan untuk transaksi kasir fisik di toko.
2. **Messaging Pattern (RabbitMQ)** memberikan ketahanan (*resilience*) dan skalabilitas bagi kanal pemesanan online WhatsApp tanpa membebani basis data inti.
3. **Outbox Pattern** menjamin pengiriman pesan event ke broker tidak akan hilang (*guaranteed delivery*) meskipun terjadi gangguan jaringan atau server restart.
