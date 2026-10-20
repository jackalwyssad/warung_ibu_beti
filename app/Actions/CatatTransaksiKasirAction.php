<?php

namespace App\Actions;

use App\Exceptions\StokTidakMencukupiException;
use App\Models\Barang;
use App\Models\ItemTransaksiKasir;
use App\Models\Pembayaran;
use App\Models\TransaksiKasir;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gaya Integrasi: Shared Database (Kasir POS <-> Sistem Stok)
 * Sesuai Tugas 3: Kasir POS dan Stok berjalan dalam satu database bersama.
 * Transaksi dieksekusi secara ACID real-time dengan pessimistic row locking (lockForUpdate)
 * untuk menjamin stok langsung berkurang saat transaksi kasir selesai.
 */
class CatatTransaksiKasirAction
{
    public function execute(array $data): TransaksiKasir
    {
        return DB::transaction(function () use ($data) {
            $nomorTransaksi = 'TRX-' . date('Ymd') . '-' . strtoupper(Str::random(5));
            $totalBelanja = 0;
            $itemsToInsert = [];

            foreach ($data['items'] as $itemInput) {
                // Shared Database: Mengunci row barang langsung untuk menjaga konsistensi ACID
                $barang = Barang::where('sku', $itemInput['sku'])->lockForUpdate()->firstOrFail();

                if ($barang->stok < $itemInput['qty']) {
                    throw new StokTidakMencukupiException(
                        $barang->sku,
                        $barang->nama_barang,
                        $barang->stok,
                        (int) $itemInput['qty']
                    );
                }

                // Potong stok langsung di database bersama (Shared Database real-time)
                $barang->decrement('stok', $itemInput['qty']);

                $subtotal = $itemInput['qty'] * $barang->harga_jual;
                $totalBelanja += $subtotal;

                $itemsToInsert[] = [
                    'barang_id' => $barang->id,
                    'sku' => $barang->sku,
                    'nama_barang' => $barang->nama_barang,
                    'qty' => $itemInput['qty'],
                    'harga_satuan' => $barang->harga_jual,
                    'subtotal' => $subtotal,
                ];
            }

            // Simpan header transaksi kasir
            $transaksi = TransaksiKasir::create([
                'nomor_transaksi' => $nomorTransaksi,
                'tanggal_transaksi' => now(),
                'total_belanja' => $totalBelanja,
                'metode_bayar' => $data['metode_bayar'] ?? 'tunai',
                'status_bayar' => 'lunas',
                'kasir_nama' => $data['kasir_nama'] ?? 'Kasir Utama',
                'catatan' => $data['catatan'] ?? null,
            ]);

            // Simpan item transaksi
            foreach ($itemsToInsert as $it) {
                ItemTransaksiKasir::create(array_merge($it, [
                    'transaksi_kasir_id' => $transaksi->id,
                ]));
            }

            // Catat Pembayaran (Tunai atau QRIS)
            $uangDiterima = (int) ($data['uang_diterima'] ?? $totalBelanja);
            $kembalian = max(0, $uangDiterima - $totalBelanja);

            Pembayaran::create([
                'nomor_referensi' => 'PAY-' . strtoupper(Str::random(8)),
                'payable_id' => $transaksi->id,
                'payable_type' => TransaksiKasir::class,
                'metode' => $data['metode_bayar'] ?? 'tunai',
                'total_tagihan' => $totalBelanja,
                'uang_diterima' => $uangDiterima,
                'kembalian' => $kembalian,
                'qr_string' => ($data['metode_bayar'] ?? 'tunai') === 'qris' ? '00020101021226...WARUNG-BETI-QRIS' : null,
                'status' => 'sukses',
            ]);

            return $transaksi->load(['items', 'pembayaran']);
        });
    }
}
