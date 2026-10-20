<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CatatTransaksiKasirAction;
use App\Exceptions\StokTidakMencukupiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatatTransaksiKasirRequest;
use App\Models\TransaksiKasir;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KasirController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $transaksi = TransaksiKasir::with(['items', 'pembayaran'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $transaksi,
        ]);
    }

    public function store(CatatTransaksiKasirRequest $request, CatatTransaksiKasirAction $action): JsonResponse
    {
        try {
            $transaksi = $action->execute($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Transaksi kasir berhasil dicatat. Stok barang langsung berkurang secara real-time via Shared Database.',
                'gaya_integrasi' => 'Shared Database (Kasir POS <-> Stok Inventori)',
                'data' => $transaksi,
            ], 201);
        } catch (StokTidakMencukupiException $e) {
            return response()->json([
                'type' => 'https://tools.ietf.org/html/rfc9457',
                'title' => 'Stok Tidak Mencukupi',
                'status' => 422,
                'detail' => $e->getMessage(),
                'sku' => $e->sku,
            ], 422);
        }
    }
}
