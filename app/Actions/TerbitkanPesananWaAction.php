<?php

namespace App\Actions;

use App\Messaging\PenerbitPesanan;
use App\Models\Barang;
use App\Models\ItemPesananWa;
use App\Models\OutboxKirim;
use App\Models\PesananWa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gaya Integrasi: Messaging (RabbitMQ) (Bot WhatsApp <-> Sistem Stok)
 * Sesuai Tugas 3: Bot WhatsApp bersifat loose-coupling dan asinkron.
 * Pesanan WhatsApp diterima, dicatat sebagai draft, lalu diterbitkan sebagai pesan event ke RabbitMQ
 * untuk diproses secara asinkron di antrean tanpa memblokir bot obrolan WhatsApp pelanggan.
 */
class TerbitkanPesananWaAction
{
    public function __construct(private readonly PenerbitPesanan $penerbit) {}

    public function execute(array $data): PesananWa
    {
        return DB::transaction(function () use ($data) {
            $nomorPesanan = 'WA-' . date('Ymd') . '-' . strtoupper(Str::random(5));
            $totalNilai = 0;
            $itemsPayload = [];

            foreach ($data['items'] as $itemInput) {
                $barang = Barang::where('sku', $itemInput['sku'])->firstOrFail();
                $subtotal = $itemInput['qty'] * $barang->harga_jual;
                $totalNilai += $subtotal;

                $itemsPayload[] = [
                    'barang_id' => $barang->id,
                    'sku' => $barang->sku,
                    'nama_barang' => $barang->nama_barang,
                    'qty' => (int) $itemInput['qty'],
                    'harga_satuan' => $barang->harga_jual,
                    'subtotal' => $subtotal,
                ];
            }

            // Simpan pesanan awal (status pending / antrean)
            $pesananWa = PesananWa::create([
                'nomor_pesanan_wa' => $nomorPesanan,
                'nomor_telepon' => $data['nomor_telepon'],
                'nama_pelanggan' => $data['nama_pelanggan'],
                'alamat_antar' => $data['alamat_antar'] ?? null,
                'status' => 'pending',
                'total_nilai' => $totalNilai,
                'metode_bayar' => $data['metode_bayar'] ?? 'tunai',
            ]);

            foreach ($itemsPayload as $it) {
                ItemPesananWa::create(array_merge($it, [
                    'pesanan_wa_id' => $pesananWa->id,
                ]));
            }

            // Siapkan payload event asinkron
            $eventData = [
                'pesanan_wa_id' => $pesananWa->id,
                'nomor_pesanan_wa' => $pesananWa->nomor_pesanan_wa,
                'nomor_telepon' => $pesananWa->nomor_telepon,
                'nama_pelanggan' => $pesananWa->nama_pelanggan,
                'total_nilai' => $pesananWa->total_nilai,
                'metode_bayar' => $pesananWa->metode_bayar,
                'items' => $itemsPayload,
                'waktu_pesan' => now()->toIso8601String(),
            ];

            // Tulis ke Outbox Pattern dalam transaksi atomik
            $eventId = (string) Str::uuid();
            OutboxKirim::create([
                'event_id' => $eventId,
                'event_type' => 'PesananWhatsAppMasuk',
                'routing_key' => 'wa.pesanan.masuk',
                'payload' => $eventData,
                'status' => 'pending',
            ]);

            return $pesananWa->load('items');
        });
    }
}
