<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TerbitkanPesananWaAction;
use App\Exceptions\StokTidakMencukupiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\KirimPesananWaRequest;
use App\Models\Barang;
use App\Models\OutboxKirim;
use App\Models\PesananWa;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BotWhatsAppController extends Controller
{
    public function store(KirimPesananWaRequest $request, TerbitkanPesananWaAction $action): JsonResponse
    {
        try {
            $pesanan = $action->execute($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Pesanan WhatsApp berhasil diterima dan diterbitkan ke antrean pesan asinkron (RabbitMQ).',
                'gaya_integrasi' => 'Messaging (Bot WhatsApp <-> Stok Inventori)',
                'data' => [
                    'id' => $pesanan->id,
                    'nomor_pesanan_wa' => $pesanan->nomor_pesanan_wa,
                    'status' => $pesanan->status,
                    'nama_pelanggan' => $pesanan->nama_pelanggan,
                    'total_nilai' => $pesanan->total_nilai,
                    'metode_bayar' => $pesanan->metode_bayar,
                    'items' => $pesanan->items,
                ],
            ], 202); // 202 Accepted (Asynchronous Processing)
        } catch (StokTidakMencukupiException $e) {
            return response()->json([
                'type' => 'https://tools.ietf.org/html/rfc9457',
                'title' => 'Stok Tidak Mencukupi',
                'status' => 422,
                'detail' => $e->getMessage(),
                'sku' => $e->sku,
                'stok_tersedia' => $e->stokTersedia,
            ], 422);
        }
    }

    public function show(string $nomor): JsonResponse
    {
        $pesanan = PesananWa::with(['items', 'pembayaran'])
            ->where('nomor_pesanan_wa', $nomor)
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $pesanan,
        ]);
    }

    public function konfirmasi(string $nomor): JsonResponse
    {
        $query = PesananWa::with('items');
        if (in_array(strtolower($nomor), ['latest', 'terbaru', 'terakhir', 'acc', 'ya'])) {
            $pesanan = $query->where('status', 'pending')->latest('id')->first();
            if (!$pesanan) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ada pesanan pending yang menunggu konfirmasi saat ini.',
                ], 404);
            }
        } elseif (is_numeric($nomor)) {
            $pesanan = $query->where('id', (int) $nomor)->firstOrFail();
        } else {
            $pesanan = $query->where('nomor_pesanan_wa', $nomor)->firstOrFail();
        }

        if ($pesanan->status === 'diproses' || $pesanan->status === 'dikonfirmasi') {
            return response()->json([
                'status' => 'error',
                'message' => "Pesanan {$nomor} sudah dikonfirmasi sebelumnya.",
                'data' => $pesanan,
            ], 400);
        }

        if ($pesanan->status === 'dibatalkan') {
            return response()->json([
                'status' => 'error',
                'message' => "Pesanan {$nomor} sudah dibatalkan dan tidak dapat dikonfirmasi.",
                'data' => $pesanan,
            ], 400);
        }

        try {
            DB::transaction(function () use ($pesanan) {
                // Kunci & potong stok di database bersama (Shared DB ACID)
                foreach ($pesanan->items as $item) {
                    $barang = Barang::where('id', $item->barang_id)->lockForUpdate()->firstOrFail();

                    if ($barang->stok < $item->qty) {
                        throw new StokTidakMencukupiException(
                            $barang->sku,
                            $barang->nama_barang,
                            $barang->stok,
                            $item->qty
                        );
                    }

                    $barang->decrement('stok', $item->qty);
                }

                $pesanan->status = 'diproses';
                $pesanan->save();

                // Catat ke Outbox & kirim ke antrean RabbitMQ
                OutboxKirim::create([
                    'event_id' => (string) Str::uuid(),
                    'event_type' => 'PesananWhatsAppDikonfirmasi',
                    'routing_key' => 'wa.pesanan.dikonfirmasi',
                    'payload' => [
                        'pesanan_wa_id' => $pesanan->id,
                        'nomor_pesanan_wa' => $pesanan->nomor_pesanan_wa,
                        'nomor_telepon' => $pesanan->nomor_telepon,
                        'status' => 'diproses',
                    ],
                    'status' => 'terkirim',
                    'sent_at' => now(),
                ]);
            });

            return response()->json([
                'status' => 'success',
                'message' => "Pesanan {$nomor} berhasil disetujui & dikonfirmasi oleh Admin. Stok online/gudang telah resmi dipotong.",
                'data' => $pesanan->fresh(['items']),
            ]);
        } catch (StokTidakMencukupiException $e) {
            return response()->json([
                'type' => 'https://tools.ietf.org/html/rfc9457',
                'title' => 'Stok Tidak Mencukupi',
                'status' => 422,
                'detail' => $e->getMessage(),
                'sku' => $e->sku,
                'stok_tersedia' => $e->stokTersedia,
            ], 422);
        }
    }

    public function tolak(string $nomor): JsonResponse
    {
        $query = PesananWa::query();
        if (in_array(strtolower($nomor), ['latest', 'terbaru', 'terakhir', 'tolak', 'batal'])) {
            $pesanan = $query->where('status', 'pending')->latest('id')->first();
            if (!$pesanan) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ada pesanan pending yang dapat ditolak saat ini.',
                ], 404);
            }
        } elseif (is_numeric($nomor)) {
            $pesanan = $query->where('id', (int) $nomor)->firstOrFail();
        } else {
            $pesanan = $query->where('nomor_pesanan_wa', $nomor)->firstOrFail();
        }
        $pesanan->status = 'dibatalkan';
        $pesanan->save();

        return response()->json([
            'status' => 'success',
            'message' => "Pesanan {$nomor} berhasil ditolak/dibatalkan.",
            'data' => $pesanan,
        ]);
    }
}
