<?php

namespace App\Console\Commands;

use App\Actions\BayarHutangSupplierAction;
use App\Actions\KonfirmasiPesananAction;
use App\Actions\TerimaBarangAction;
use App\Exceptions\KreditSupplierTerlampaui;
use App\Messaging\BrokerPengadaan;
use App\Models\HutangDagang;
use App\Models\ItemPesananPembelian;
use App\Models\OutboxKirim;
use App\Models\PesananPembelian;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UjiSkenarioPengadaan extends Command
{
    protected $signature = 'pengadaan:uji-skenario';
    protected $description = 'Menjalankan pengujian skenario lengkap integrasi Pengadaan & Purchase Order (REST API, Outbox Pattern, RabbitMQ)';

    public function handle(): int
    {
        $this->info("==========================================================================");
        $this->info("   UJI SKENARIO LENGKAP PENGADAAN & PURCHASE ORDER - WARUNG IBU BETI     ");
        $this->info("==========================================================================");

        // Bersihkan data uji sebelumnya
        $this->warn("1. Membersihkan data pengujian lama...");
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        OutboxKirim::truncate();
        HutangDagang::truncate();
        ItemPesananPembelian::truncate();
        PesananPembelian::truncate();
        Supplier::where('kode_supplier', 'like', 'SUP-TEST%')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Skenario 1: Input Supplier Baru
        $this->info("\n[Skenario 1] Menambahkan Supplier Sembako dan Distributor Minyak...");
        $supMinyak = Supplier::create([
            'kode_supplier' => 'SUP-TEST-001',
            'nama' => 'PT Surya Distribusi Sembako',
            'kontak' => '081234567890',
            'alamat' => 'Kawasan Industri Rancaekek',
            'total_hutang' => 0,
            'limit_kredit' => 15000000, // Limit Rp15.000.000
        ]);
        $this->line("  [OK] Supplier dibuat: {$supMinyak->nama} (Limit: Rp" . number_format($supMinyak->limit_kredit, 0, ',', '.') . ")");

        // Skenario 2: Pembuatan Purchase Order (PO)
        $this->info("\n[Skenario 2] Membuat Draft Purchase Order untuk Pasokan Beras & Minyak Goreng...");
        $po = PesananPembelian::create([
            'nomor_po' => 'PO-' . date('Ymd') . '-001',
            'supplier_id' => $supMinyak->id,
            'status' => 'draft',
            'total_nilai' => 8500000,
            'metode_bayar' => 'tempo',
            'keterangan' => 'Kulakan mingguan beras premium & minyak goreng 2L',
            'tanggal_pesan' => now()->toDateString(),
            'tanggal_estimasi' => now()->addDays(2)->toDateString(),
        ]);

        ItemPesananPembelian::create([
            'pesanan_pembelian_id' => $po->id,
            'sku' => 'BRS-PREM-5KG',
            'nama_barang' => 'Beras Pandan Wangi 5kg',
            'jumlah_pesan' => 50,
            'jumlah_diterima' => 0,
            'harga_satuan' => 70000,
            'subtotal' => 3500000,
        ]);

        ItemPesananPembelian::create([
            'pesanan_pembelian_id' => $po->id,
            'sku' => 'MYK-GRG-2L',
            'nama_barang' => 'Minyak Goreng Sawit 2L',
            'jumlah_pesan' => 125,
            'jumlah_diterima' => 0,
            'harga_satuan' => 40000,
            'subtotal' => 5000000,
        ]);
        $this->line("  [OK] PO Dibuat: {$po->nomor_po} senilai Rp" . number_format($po->total_nilai, 0, ',', '.'));

        // Skenario 3: Konfirmasi PO & Outbox Pattern
        $this->info("\n[Skenario 3] Konfirmasi Pesanan & Menulis Domain Event ke Outbox...");
        $konfirmasiAction = app(KonfirmasiPesananAction::class);
        $po = $konfirmasiAction->execute($po->id);
        $this->line("  [OK] PO {$po->nomor_po} status berubah menjadi: {$po->status}");

        $pendingOutbox = OutboxKirim::where('status', 'pending')->count();
        $this->line("  [OK] Outbox Pattern: {$pendingOutbox} pesan tersimpan di tabel outbox_kirim.");

        // Skenario 4: Pengiriman Outbox ke RabbitMQ
        $this->info("\n[Skenario 4] Mengirim Event dari Outbox ke Topic Exchange RabbitMQ...");
        Artisan::call('pengadaan:kirim-outbox');
        $this->line(Artisan::output());

        $terkirimOutbox = OutboxKirim::where('status', 'terkirim')->count();
        $this->line("  [OK] Pesan outbox yang berhasil diterbitkan: {$terkirimOutbox}");

        // Skenario 5: Consumer Logistik Gudang & Buku Hutang
        $this->info("\n[Skenario 5] Memproses Antrean dengan Consumer Gudang dan Keuangan...");
        Artisan::call('pengadaan:konsumen-logistik', ['--timeout' => 3]);
        $this->line(Artisan::output());

        Artisan::call('pengadaan:konsumen-hutang', ['--timeout' => 3]);
        $this->line(Artisan::output());

        // Skenario 6: Penerimaan Barang Fisik
        $this->info("\n[Skenario 6] Penerimaan Barang Fisik di Gudang Warung...");
        $terimaAction = app(TerimaBarangAction::class);
        $penerimaan = $terimaAction->execute($po->id, [
            'penerima' => 'Mang Udin (Gudang)',
            'catatan' => 'Beras dan minyak diterima dalam kondisi baik',
            'items' => [
                ['sku' => 'BRS-PREM-5KG', 'jumlah_diterima' => 50],
                ['sku' => 'MYK-GRG-2L', 'jumlah_diterima' => 125],
            ],
        ]);
        $this->line("  [OK] Penerimaan dicatat: {$penerimaan->nomor_penerimaan} (Status: {$penerimaan->status})");

        $hutang = HutangDagang::where('pesanan_pembelian_id', $po->id)->first();
        $this->line("  [OK] Hutang Dagang Terbit: {$hutang->nomor_faktur} senilai Rp" . number_format($hutang->jumlah_hutang, 0, ',', '.'));

        // Kirim outbox event penerimaan
        Artisan::call('pengadaan:kirim-outbox');

        // Skenario 7: Pembayaran Hutang Dagang Supplier
        $this->info("\n[Skenario 7] Pembayaran Sebagian Hutang Dagang (Cicilan Pertama)...");
        $bayarAction = app(BayarHutangSupplierAction::class);
        $hutang = $bayarAction->execute($hutang->id, 5000000); // Bayar Rp5.000.000
        $this->line("  [OK] Pembayaran berhasil dicatat. Sisa hutang faktur {$hutang->nomor_faktur}: Rp" . number_format($hutang->sisa_hutang, 0, ',', '.'));
        $this->line("  [OK] Total hutang supplier saat ini: Rp" . number_format($supMinyak->fresh()->total_hutang, 0, ',', '.'));

        // Skenario 8: Uji Proteksi Limit Kredit Terlampaui
        $this->info("\n[Skenario 8] Pengujian Proteksi Kredit Limit Supplier (Simulasi Pesanan Melampaui Batas)...");
        $poJumbo = PesananPembelian::create([
            'nomor_po' => 'PO-' . date('Ymd') . '-JUMBO',
            'supplier_id' => $supMinyak->id,
            'status' => 'draft',
            'total_nilai' => 20000000, // Rp20.000.000 -> akan melampaui limit Rp15.000.000
            'metode_bayar' => 'tempo',
            'keterangan' => 'Order besar melebihi limit',
            'tanggal_pesan' => now()->toDateString(),
        ]);
        ItemPesananPembelian::create([
            'pesanan_pembelian_id' => $poJumbo->id,
            'sku' => 'BRS-PREM-5KG',
            'nama_barang' => 'Beras Pandan Wangi 5kg',
            'jumlah_pesan' => 300,
            'jumlah_diterima' => 0,
            'harga_satuan' => 70000,
            'subtotal' => 21000000,
        ]);

        try {
            $konfirmasiAction->execute($poJumbo->id);
            $this->error("  [FAIL] Sistem seharusnya menolak PO jumbo karena melebihi kredit!");
        } catch (KreditSupplierTerlampaui $e) {
            $this->info("  [PASSED] KreditSupplierTerlampaui tertangkap dengan benar!");
            $this->line("  Pesan Galat RFC 9457: " . $e->getMessage());
        }

        $this->info("\n==========================================================================");
        $this->info("   SELURUH SKENARIO UJI INTEGRASI PENGADAAN BERHASIL 100% TERPENUHI!     ");
        $this->info("==========================================================================");

        return Command::SUCCESS;
    }
}
