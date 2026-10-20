<?php

namespace App\Actions;

use App\Events\BarangDiterimaSupplier;
use App\Exceptions\PesananTidakValidException;
use App\Models\Barang;
use App\Models\HutangDagang;
use App\Models\ItemPesananPembelian;
use App\Models\OutboxKirim;
use App\Models\PenerimaanBarang;
use App\Models\PesananPembelian;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TerimaBarangAction
{
    public function execute(int $pesananId, array $data): PenerimaanBarang
    {
        return DB::transaction(function () use ($pesananId, $data) {
            $po = PesananPembelian::with('items')->where('id', $pesananId)->lockForUpdate()->firstOrFail();

            if (!in_array($po->status, ['dikonfirmasi', 'diproses'])) {
                throw new PesananTidakValidException(
                    "Penerimaan barang hanya dapat dilakukan untuk pesanan berstatus dikonfirmasi/diproses. Status: {$po->status}"
                );
            }

            $supplier = Supplier::where('id', $po->supplier_id)->lockForUpdate()->firstOrFail();

            // Generate nomor penerimaan unik
            $nomorPenerimaan = 'RCV-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            $semuaSelesai = true;
            $rincianBarang = [];
            $totalNilaiDiterima = 0;

            foreach ($data['items'] as $itemInput) {
                $itemPo = ItemPesananPembelian::where('pesanan_pembelian_id', $po->id)
                    ->where('sku', $itemInput['sku'])
                    ->firstOrFail();

                $jumlahTerima = (int) $itemInput['jumlah_diterima'];
                $itemPo->jumlah_diterima += $jumlahTerima;
                $itemPo->save();

                $subtotalDiterima = $jumlahTerima * $itemPo->harga_satuan;
                $totalNilaiDiterima += $subtotalDiterima;

                // UPDATE STOK TOKO OTOMATIS (Hubungan hulu pengadaan ke hilir stok)
                $barang = Barang::firstOrNew(['sku' => $itemPo->sku]);
                if (!$barang->exists) {
                    $barang->nama_barang = $itemPo->nama_barang;
                    $barang->kategori = 'sembako';
                    $barang->stok = 0;
                    $barang->harga_beli = $itemPo->harga_satuan;
                    $barang->harga_jual = (int) ($itemPo->harga_satuan * 1.15); // Margin 15%
                    $barang->satuan = 'pcs';
                }
                $barang->stok += $jumlahTerima;
                $barang->save();

                $rincianBarang[] = [
                    'sku' => $itemPo->sku,
                    'nama_barang' => $itemPo->nama_barang,
                    'jumlah_diterima' => $jumlahTerima,
                    'total_diterima' => $itemPo->jumlah_diterima,
                    'jumlah_pesan' => $itemPo->jumlah_pesan,
                ];

                if ($itemPo->jumlah_diterima < $itemPo->jumlah_pesan) {
                    $semuaSelesai = false;
                }
            }

            $statusPenerimaan = $semuaSelesai ? 'lengkap' : 'parsial';
            $po->status = $semuaSelesai ? 'selesai' : 'diproses';
            $po->save();

            // Buat record penerimaan barang
            $penerimaan = PenerimaanBarang::create([
                'nomor_penerimaan' => $nomorPenerimaan,
                'pesanan_pembelian_id' => $po->id,
                'tanggal_terima' => now(),
                'penerima' => $data['penerima'],
                'catatan' => $data['catatan'] ?? null,
                'status' => $statusPenerimaan,
            ]);

            // Jika pembayaran tempo, catat / perbarui Hutang Dagang
            if ($po->metode_bayar === 'tempo' && $totalNilaiDiterima > 0) {
                $faktur = 'INV-' . $po->nomor_po;
                $hutang = HutangDagang::firstOrNew(
                    ['nomor_faktur' => $faktur],
                    [
                        'supplier_id' => $supplier->id,
                        'pesanan_pembelian_id' => $po->id,
                        'jumlah_hutang' => 0,
                        'jumlah_dibayar' => 0,
                        'sisa_hutang' => 0,
                        'jatuh_tempo' => now()->addDays(30),
                        'status' => 'belum_lunas',
                    ]
                );

                $hutang->jumlah_hutang += $totalNilaiDiterima;
                $hutang->sisa_hutang = $hutang->jumlah_hutang - $hutang->jumlah_dibayar;
                $hutang->save();

                $supplier->total_hutang += $totalNilaiDiterima;
                $supplier->save();
            }

            // Buat event BarangDiterimaSupplier
            $event = new BarangDiterimaSupplier(
                $penerimaan->id,
                $penerimaan->nomor_penerimaan,
                $po->nomor_po,
                $supplier->kode_supplier,
                $supplier->nama,
                $rincianBarang,
                $statusPenerimaan,
                $penerimaan->penerima,
                $penerimaan->tanggal_terima->toIso8601String()
            );

            OutboxKirim::create([
                'event_id' => (string) Str::uuid(),
                'event_type' => 'BarangDiterimaSupplier',
                'routing_key' => 'pengadaan.barang.diterima',
                'payload' => $event->toArray(),
                'status' => 'pending',
                'retry_count' => 0,
            ]);

            return $penerimaan;
        });
    }
}
