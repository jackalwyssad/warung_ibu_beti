<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TerimaBarangAction;
use App\Exceptions\PesananTidakValidException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TerimaBarangRequest;
use App\Http\Resources\PenerimaanBarangResource;
use App\Models\PenerimaanBarang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PenerimaanBarangController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PenerimaanBarang::with('pesananPembelian.supplier');

        if ($poId = $request->query('pesanan_pembelian_id')) {
            $query->where('pesanan_pembelian_id', $poId);
        }

        return PenerimaanBarangResource::collection($query->latest()->paginate(15));
    }

    public function store(int $pesananId, TerimaBarangRequest $request, TerimaBarangAction $action): JsonResponse
    {
        try {
            $penerimaan = $action->execute($pesananId, $request->validated());
            return (new PenerimaanBarangResource($penerimaan))->response()->setStatusCode(201);
        } catch (PesananTidakValidException $e) {
            return response()->json([
                'type' => 'https://tools.ietf.org/html/rfc9457',
                'title' => 'Penerimaan Tidak Valid',
                'status' => 400,
                'detail' => $e->getMessage(),
            ], 400);
        }
    }
}
