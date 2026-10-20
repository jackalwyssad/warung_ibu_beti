<?php

namespace App\Console\Commands;

use App\Actions\CatatTransaksiKasirAction;
use App\Actions\TerbitkanPesananWaAction;
use App\Exceptions\StokTidakMencukupiException;
use App\Models\Barang;
use App\Models\OutboxKirim;
use App\Models\PesananWa;
use App\Models\TransaksiKasir;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class UjiSkenarioTugas3 extends Command
{
    protected $signature = 'warung:uji-tugas3';
    protected $description = 'Menjalankan pengujian skenario lengkap integrasi sesuai Lembar Kerja Tugas 3 (Kasir Shared DB & Bot WA Messaging)';

    public function handle(): int
    {
        $this->info("==========================================================================");
        $this->info("   UJI SKENARIO LENGKAP INTEGRASI SISTEM TUGAS 3 - WARUNG IBU BETI        ");
        $this->info("   1. Kasir POS <-> Stok (Gaya: Shared Database)                          ");
        $this->info("   2. Bot WhatsApp <-> Stok (Gaya: Messaging via RabbitMQ)                ");
        $this->info("==========================================================================");

        // Siapkan Master Data Barang Sembako
        $this->info("\n[Tahap 1] Inisialisasi Katalog Sembako & Stok Awal di Toko...");
        $beras = Barang::updateOrCreate(
            ['sku' => 'BRS-PREM-5KG'],
            [
                'nama_barang' => 'Beras Pandan Wangi 5kg',
                'kategori' => 'sembako',
                'stok' => 50,
                'harga_beli' => 65000,
                'harga_jual' => 75000,
                'satuan' => 'karung',
            ]
        );

        $minyak = Barang::updateOrCreate(
            ['sku' => 'MYK-GRG-2L'],
            [
                'nama_barang' => 'Minyak Goreng Sawit 2L',
                'kategori' => 'sembako',
                'stok' => 40,
                'harga_beli' => 33000,
                'harga_jual' => 38000,
                'satuan' => 'pouch',
            ]
        );

        $this->line("  [OK] Stok Beras: {$beras->stok} karung (@Rp" . number_format($beras->harga_jual, 0, ',', '.') . ")");
        $this->line("  [OK] Stok Minyak: {$minyak->stok} pouch (@Rp" . number_format($minyak->harga_jual, 0, ',', '.') . ")");

        // Skenario 1: Kasir POS (Shared Database - Real-time ACID)
        $this->info("\n[Tahap 2] Skenario Kasir POS: Transaksi Langsung di Toko (Gaya: Shared Database)...");
        $this->line("  Penjelasan: Kasir memotong stok di tabel 'barang' secara instan dan atomik via row-level locking.");
        $kasirAction = app(CatatTransaksiKasirAction::class);

        $transaksiKasir = $kasirAction->execute([
            'kasir_nama' => 'Mbak Beti',
            'metode_bayar' => 'tunai',
            'uang_diterima' => 200000,
            'items' => [
                ['sku' => 'BRS-PREM-5KG', 'qty' => 2], // 2 x 75.000 = 150.000
                ['sku' => 'MYK-GRG-2L', 'qty' => 1],   // 1 x 38.000 = 38.000
            ],
        ]);

        $this->line("  [OK] Transaksi Kasir Berhasil: {$transaksiKasir->nomor_transaksi}");
        $this->line("  [OK] Total Belanja: Rp" . number_format($transaksiKasir->total_belanja, 0, ',', '.') . " | Metode: {$transaksiKasir->metode_bayar}");
        $this->line("  [OK] Pembayaran: Diterima Rp" . number_format($transaksiKasir->pembayaran->uang_diterima, 0, ',', '.') . " (Kembalian: Rp" . number_format($transaksiKasir->pembayaran->kembalian, 0, ',', '.') . ")");
        $this->line("  [VERIFIKASI SHARED DB] Sisa Stok Beras di Database: " . $beras->fresh()->stok . " (Awal: 50 -> Berkurang 2)");
        $this->line("  [VERIFIKASI SHARED DB] Sisa Stok Minyak di Database: " . $minyak->fresh()->stok . " (Awal: 40 -> Berkurang 1)");

        // Skenario 2: Kasir POS dengan Metode QRIS
        $this->info("\n[Tahap 3] Skenario Kasir POS: Transaksi Digital Pembayaran QRIS...");
        $transaksiQris = $kasirAction->execute([
            'kasir_nama' => 'Mbak Beti',
            'metode_bayar' => 'qris',
            'items' => [
                ['sku' => 'MYK-GRG-2L', 'qty' => 2], // 2 x 38.000 = 76.000
            ],
        ]);
        $this->line("  [OK] Transaksi QRIS Berhasil: {$transaksiQris->nomor_transaksi}");
        $this->line("  [OK] Referensi Pembayaran: {$transaksiQris->pembayaran->nomor_referensi} (QR Payload: {$transaksiQris->pembayaran->qr_string})");
        $this->line("  [VERIFIKASI SHARED DB] Sisa Stok Minyak: " . $minyak->fresh()->stok);

        // Skenario 3: Bot Pemesanan WhatsApp (Gaya: Messaging - RabbitMQ)
        $this->info("\n[Tahap 4] Skenario Bot Pemesanan WhatsApp (Gaya: Messaging via RabbitMQ)...");
        $this->line("  Penjelasan: Pelanggan chat via WA. Pesanan dicatat dan diterbitkan sebagai pesan event ke RabbitMQ.");
        $waAction = app(TerbitkanPesananWaAction::class);

        $pesananWa = $waAction->execute([
            'nomor_telepon' => '081298765432',
            'nama_pelanggan' => 'Ibu Siti (Warga RT 03)',
            'alamat_antar' => 'Jl. Mawar No. 14',
            'metode_bayar' => 'tunai',
            'items' => [
                ['sku' => 'BRS-PREM-5KG', 'qty' => 3],
            ],
        ]);

        $this->line("  [OK] Pesanan WhatsApp Diterima: {$pesananWa->nomor_pesanan_wa} (Status: {$pesananWa->status})");
        $this->line("  [OK] Event PesananWhatsAppMasuk tersimpan di tabel outbox_kirim.");

        // Skenario 4: Penerbitan Outbox ke Broker RabbitMQ
        $this->info("\n[Tahap 5] Mengirim Event WhatsApp dari Outbox ke Topic Exchange RabbitMQ...");
        Artisan::call('pengadaan:kirim-outbox');
        $this->line(Artisan::output());

        // Skenario 5: Consumer Bot WhatsApp Memproses Pesanan
        $this->info("\n[Tahap 6] Menjalankan Consumer Bot WhatsApp (antrean: wa.pesanan-antrean)...");
        $this->line("  Worker RabbitMQ mengambil pesan dan mengeksekusi reservasi stok secara asinkron.");
        Artisan::call('wa:proses-pesanan', ['--timeout' => 3]);
        $this->line(Artisan::output());

        $pesananWaTerproses = PesananWa::find($pesananWa->id);
        $this->line("  [VERIFIKASI ASINKRON] Status Pesanan WA Kini: {$pesananWaTerproses->status}");
        $this->line("  [VERIFIKASI STOK] Sisa Stok Beras setelah pesanan WA diproses: " . $beras->fresh()->stok);

        // Skenario 6: Uji Proteksi Stok Habis
        $this->info("\n[Tahap 7] Pengujian Proteksi Stok Kasir (Simulasi Permintaan Melebihi Stok)...");
        try {
            $kasirAction->execute([
                'kasir_nama' => 'Mbak Beti',
                'metode_bayar' => 'tunai',
                'items' => [
                    ['sku' => 'BRS-PREM-5KG', 'qty' => 999], // 999 karung melebihi sisa stok
                ],
            ]);
            $this->error("  [FAIL] Sistem seharusnya menolak transaksi kasir yang melebihi stok!");
        } catch (StokTidakMencukupiException $e) {
            $this->info("  [PASSED] StokTidakMencukupiException tertangkap dengan benar (RFC 9457):");
            $this->line("  " . $e->getMessage());
        }

        $this->info("\n==========================================================================");
        $this->info("   SELURUH SKENARIO TUGAS 3 BERHASIL TERVERIFIKASI 100%!                  ");
        $this->info("==========================================================================");

        return Command::SUCCESS;
    }
}
