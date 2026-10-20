<?php

namespace App\Actions;

use App\Events\PembayaranHutangDicatat;
use App\Exceptions\PesananTidakValidException;
use App\Models\HutangDagang;
use App\Models\OutboxKirim;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BayarHutangSupplierAction
{
    public function execute(int $hutangId, int $jumlahBayar): HutangDagang
    {
        return DB::transaction(function () use ($hutangId, $jumlahBayar) {
            $hutang = HutangDagang::with('supplier')->where('id', $hutangId)->lockForUpdate()->firstOrFail();

            if ($hutang->status === 'lunas') {
                throw new PesananTidakValidException("Faktur {$hutang->nomor_faktur} sudah lunas!");
            }

            if ($jumlahBayar <= 0) {
                throw new PesananTidakValidException("Nominal pembayaran harus lebih dari 0!");
            }

            if ($jumlahBayar > $hutang->sisa_hutang) {
                throw new PesananTidakValidException(
                    "Nominal pembayaran (Rp" . number_format($jumlahBayar, 0, ',', '.') .
                    ") melebihi sisa hutang (Rp" . number_format($hutang->sisa_hutang, 0, ',', '.') . ")!"
                );
            }

            $hutang->jumlah_dibayar += $jumlahBayar;
            $hutang->sisa_hutang = $hutang->jumlah_hutang - $hutang->jumlah_dibayar;
            if ($hutang->sisa_hutang <= 0) {
                $hutang->status = 'lunas';
            }
            $hutang->save();

            // Potong total hutang supplier
            $supplier = Supplier::where('id', $hutang->supplier_id)->lockForUpdate()->firstOrFail();
            $supplier->total_hutang = max(0, $supplier->total_hutang - $jumlahBayar);
            $supplier->save();

            // Buat event PembayaranHutangDicatat
            $event = new PembayaranHutangDicatat(
                $hutang->id,
                $hutang->nomor_faktur,
                $supplier->kode_supplier,
                $supplier->nama,
                $jumlahBayar,
                $hutang->sisa_hutang,
                $hutang->status,
                now()->toIso8601String()
            );

            // Simpan ke outbox pattern
            OutboxKirim::create([
                'event_id' => (string) Str::uuid(),
                'event_type' => 'PembayaranHutangDicatat',
                'routing_key' => 'pengadaan.hutang.dibayar',
                'payload' => $event->toArray(),
                'status' => 'pending',
                'retry_count' => 0,
            ]);

            return $hutang->load('supplier');
        });
    }
}
