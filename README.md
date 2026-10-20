# Sistem Terintegrasi Warung Ibu Beti ? Tugas 3 & Proyek Integrasi Sistem

**Mata Kuliah:** Integrasi Sistem dan Komputasi Awan (25IF1504)  
**Institusi:** STMIK Bandung  
**Kelompok:** Intel  

### Anggota Kelompok:
1. **Jacky Firmansah** (1224010)
2. **Arya Kusuma Dinata** (1224005)
3. **Mochamad Aldi Ardiansyah** (1224020)
4. **Andhi Yudha Permana** (1224017)

---

## ?? Ringkasan Arsitektur Sistem (Sesuai Lembar Kerja Tugas 3)

Sistem ini dibangun berdasarkan hasil analisis **Lembar Kerja Tugas 3: Memilih Gaya Integrasi**, yang mengintegrasikan 4 komponen utama Warung Ibu Beti ke dalam dua gaya integrasi yang berbeda sesuai karakteristik bisnisnya:

1. **Pasangan 1: Sistem Kasir (POS) ? Sistem Stok/Inventori (Gaya: Shared Database)**
   - **Karakteristik:** *Tight coupling*, *real-time synchronous*, intrusiveness sedang.
   - **Justifikasi:** Kasir fisik dan persediaan toko beroperasi di tempat dan waktu yang sama di toko. Setiap transaksi tunai maupun QRIS di kasir langsung memotong stok di tabel `barang` secara atomik (*pessimistic row locking* `SELECT ... FOR UPDATE`), sehingga mencegah barang terjual ganda tanpa latensi antrean.

2. **Pasangan 2: Bot Pemesanan WhatsApp ? Sistem Stok/Inventori (Gaya: Messaging via RabbitMQ)**
   - **Karakteristik:** *Loose coupling*, *asynchronous*, *low intrusiveness*.
   - **Justifikasi:** Kanal WhatsApp bersifat asinkron dan terpisah dari server fisik toko. Pesanan dari bot WhatsApp disimpan sebagai draft dan diterbitkan sebagai pesan event ke **Topic Exchange RabbitMQ** (`wa.pesanan.masuk`). Consumer asinkron (`wa:proses-pesanan`) mengambil antrean dan mereservasi stok di belakang layar. Hal ini memungkinkan penambahan kanal baru (misal Web Store) di masa depan tanpa membongkar sistem stok.

3. **Komponen Pendukung: Sistem Pengadaan & Restock Supplier (Hilir ke Hulu)**
   - Melengkapi siklus hidup inventori toko: saat barang diterima dari supplier melalui Purchase Order (PO), stok barang di tabel `barang` otomatis bertambah.

---

## ??? 1. Diagram Arsitektur Integrasi Sistem

```mermaid
flowchart TB
    subgraph Klien ["1. Kanal Pengguna & Kasir"]
        PelangganToko["?? Pelanggan Toko Fisik"]
        PelangganWA["?? Pelanggan Online WhatsApp"]
        Kasir["?? Kasir Toko"]
        SupplierPemasok["?? Pemasok / Supplier Sembako"]
    end

    subgraph CoreService ["2. Layanan Inti Laravel API (Port 8090)"]
        direction TB
        APIPos["?? Modul Kasir POS"]
        APIWa["?? Modul Webhook Bot WhatsApp"]
        APIStok["?? Modul Inventori Sembako"]
        APIPengadaan["?? Modul Pengadaan / PO"]
        OutboxWorker["?? Outbox Publisher Worker"]
        WAWorker["?? Worker Konsumen WhatsApp"]
    end

    subgraph DataStore ["3. Shared Database MySQL (Port 3309)"]
        direction TB
        TBarang[("??? barang (Stok Real-time)")]
        TKasir[("??? transaksi_kasir & pembayaran")]
        TWa[("??? pesanan_wa")]
        TOutbox[("??? outbox_kirim")]
        TPo[("??? pesanan_pembelian & supplier")]
    end

    subgraph Broker ["4. Message Broker RabbitMQ (Port 5676 & 15676)"]
        direction TB
        TopicEx[["?? Topic Exchange: warung.pengadaan.events"]]
        QWA[("?? Antrean: wa.pesanan-antrean")]
        QGudang[("?? Antrean: gudang.masuk")]
        QHutang[("?? Antrean: keuangan.buku-hutang")]
        ExDLX[["?? Fanout DLX: warung.pengadaan.dlx"]]
    end

    %% Relasi Kasir (Shared DB)
    PelangganToko --> Kasir
    Kasir -->|1. Input Belanja| APIPos
    APIPos -->|Gaya: SHARED DATABASE (Pessimistic Lock & Decrement)| TBarang
    APIPos -->|Simpan Transaksi & Bayar QRIS/Tunai| TKasir

    %% Relasi Bot WA (Messaging)
    PelangganWA -->|2. Order via WA| APIWa
    APIWa -->|Simpan Draft| TWa
    APIWa -->|Gaya: MESSAGING (Tulis Event Atomik)| TOutbox
    TOutbox -.->|Polling Event Pending| OutboxWorker
    OutboxWorker -->|Publish Event wa.pesanan.masuk| TopicEx
    TopicEx -->|Binding wa.pesanan.*| QWA
    QWA -->|Ambil Pesan Asinkron| WAWorker
    WAWorker -->|Reservasi Stok & Ubah Status| TBarang

    %% Relasi Pengadaan Supplier
    SupplierPemasok --> APIPengadaan
    APIPengadaan --> TPo
    APIPengadaan -->|Barang Masuk Tambah Stok| TBarang
```

---

## ?? 2. Alokasi Port Terisolasi (Bebas Konflik)

| Layanan | Port Host | Keterangan |
|---|---|---|
| **Aplikasi Laravel API** | `8090` | Base URL: `http://localhost:8090/api/v1` |
| **MySQL Database** | `3309` | Database: `warung_beti`, User: `sail`, Password: `password` |
| **RabbitMQ AMQP** | `5676` | Host AMQP untuk koneksi worker & publisher |
| **RabbitMQ Management Dashboard** | `15676` | URL: `http://localhost:15676` (User: `beti`, Pass: `rahasia123`) |
| **Swagger UI** | `8083` | URL: `http://localhost:8083` |
| **Vite Dev Server** | `5176` | Port frontend |

---

## ?? 3. Panduan Menjalankan & Menguji Sistem

### 1. Menjalankan Container Docker
```bash
cd ~/warung_ibu_beti
./vendor/bin/sail up -d
```

### 2. Menjalankan Uji Skenario Tugas 3 (Otomatis)
Perintah ini mengeksekusi demonstrasi integrasi lengkap sesuai Lembar Kerja Tugas 3:
```bash
./vendor/bin/sail artisan warung:uji-tugas3
```

**Cakupan Pengujian:**
1. Inisialisasi katalog dan stok sembako.
2. Transaksi Kasir POS langsung memotong stok via **Shared Database** (real-time ACID).
3. Transaksi pembayaran QRIS & Tunai.
4. Pemesanan Bot WhatsApp menerbitkan pesan ke **RabbitMQ (Messaging)**.
5. Worker background (`wa:proses-pesanan`) mengeksekusi reservasi stok secara asinkron.
6. Proteksi jika stok barang di toko tidak mencukupi (RFC 9457).

### 3. Menjalankan Automated Test Suite (PHPUnit)
```bash
./vendor/bin/sail artisan test
```
*Seluruh 12 feature & unit test lulus 100% (38 assertions).*

### 4. Menjalankan Worker Pesanan WhatsApp
Untuk memproses antrean pesan WhatsApp yang masuk:
```bash
./vendor/bin/sail artisan wa:proses-pesanan
```

---

## ?? 4. Dokumentasi Endpoint REST API

### A. Katalog Barang Sembako
- `GET /api/v1/barang` : Mengambil daftar sembako & sisa stok.
- `POST /api/v1/barang` : Menambah barang baru ke inventori toko.

### B. Kasir POS (Shared Database)
- `POST /api/v1/kasir/transaksi` : Transaksi kasir langsung (memotong stok langsung secara ACID).
- `GET /api/v1/kasir/transaksi` : Riwayat transaksi kasir dan pembayaran.

### C. Bot WhatsApp (Messaging via RabbitMQ)
- `POST /api/v1/wa/pesanan` : Menerima pesanan pelanggan dari bot WA dan menerbitkan ke RabbitMQ (`202 Accepted`).
- `GET /api/v1/wa/pesanan/{nomor}` : Memeriksa status pemrosesan pesanan WA.

### D. Pengadaan & Supplier (Restock Logistik)
- `GET /api/v1/suppliers` & `POST /api/v1/suppliers` : Manajemen supplier.
- `POST /api/v1/pesanan` & `POST /api/v1/pesanan/{id}/konfirmasi` : Purchase Order tempo & validasi limit kredit.
- `POST /api/v1/pesanan/{id}/terima-barang` : Penerimaan fisik barang kulakan (otomatis menambah stok toko).
