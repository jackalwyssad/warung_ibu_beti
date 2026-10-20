<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TerbitkanPesananWaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\KirimPesananWaRequest;
use App\Models\PesananWa;
use Illuminate\Http\JsonResponse;

class BotWhatsAppController extends Controller
{
    public function store(KirimPesananWaRequest $request, TerbitkanPesananWaAction $action): JsonResponse
    {
        $pesanan = $action->execute($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Pesanan WhatsApp berhasil diterima dan diterbitkan ke antrean pesan asinkron (RabbitMQ).',
            'gaya_integrasi' => 'Messaging (Bot WhatsApp <-> Stok Inventori)',
            'data' => [
                'nomor_pesanan_wa' => $pesanan->nomor_pesanan_wa,
                'status' => $pesanan->status,
                'nama_pelanggan' => $pesanan->nama_pelanggan,
                'total_nilai' => $pesanan->total_nilai,
                'metode_bayar' => $pesanan->metode_bayar,
                'items' => $pesanan->items,
            ],
        ], 202); // 202 Accepted (Asynchronous Processing)
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
}
