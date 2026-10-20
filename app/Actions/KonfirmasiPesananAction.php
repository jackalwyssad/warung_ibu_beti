<?php

namespace App\Actions;

use App\Events\PesananPembelianDikonfirmasi;
use App\Exceptions\KreditSupplierTerlampaui;
use App\Exceptions\PesananTidakValidException;
use App\Models\OutboxKirim;
use App\Models\PesananPembelian;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KonfirmasiPesananAction
{
    public function execute(int $pesananId): PesananPembelian
    {
        return DB::transaction(function () use ($pesananId) {
            // Lock pesanan dan supplier untuk konsistensi ACID
            $po = PesananPembelian::with('items')->where('id', $pesananId)->lockForUpdate()->firstOrFail();

            if ($po->status !== 'draft') {
                throw new PesananTidakValidException(
                    "Pesanan {$po->nomor_po} tidak dapat dikonfirmasi karena status saat ini: {$po->status}"
                );
            }

            if ($po->items->isEmpty()) {
                throw new PesananTidakValidException("Pesanan {$po->nomor_po} tidak memiliki item barang!");
            }

            $supplier = Supplier::where('id', $po->supplier_id)->lockForUpdate()->firstOrFail();

            // Jika pembayaran tempo/kredit, verifikasi limit kredit
            if ($po->metode_bayar === 'tempo') {
                $totalHutangSetelahPo = $supplier->total_hutang + $po->total_nilai;
                if ($totalHutangSetelahPo > $supplier->limit_kredit) {
                    throw new KreditSupplierTerlampaui(
                        $supplier->kode_supplier,
                        $supplier->nama,
                        $supplier->total_hutang,
                        $po->total_nilai,
                        $supplier->limit_kredit
                    );
                }
            }

            // Update status pesanan
            $po->status = 'dikonfirmasi';
            $po->save();

            // Siapkan item list untuk event
            $itemsPayload = $po->items->map(fn($it) => [
                'sku' => $it->sku,
                'nama_barang' => $it->nama_barang,
                'jumlah_pesan' => $it->jumlah_pesan,
                'harga_satuan' => $it->harga_satuan,
                'subtotal' => $it->subtotal,
            ])->toArray();

            // Buat Domain Event
            $event = new PesananPembelianDikonfirmasi(
                $po->id,
                $po->nomor_po,
                $supplier->kode_supplier,
                $supplier->nama,
                $po->total_nilai,
                $po->metode_bayar,
                $itemsPayload,
                $po->tanggal_pesan->format('Y-m-d'),
                $po->tanggal_estimasi?->format('Y-m-d')
            );

            // Simpan ke outbox dalam transaksi database yang sama (Outbox Pattern)
            OutboxKirim::create([
                'event_id' => (string) Str::uuid(),
                'event_type' => 'PesananPembelianDikonfirmasi',
                'routing_key' => 'pengadaan.po.dikonfirmasi',
                'payload' => $event->toArray(),
                'status' => 'pending',
                'retry_count' => 0,
            ]);

            return $po->load(['supplier', 'items']);
        });
    }
}
