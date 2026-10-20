<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\BayarHutangSupplierAction;
use App\Exceptions\PesananTidakValidException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BayarHutangRequest;
use App\Http\Resources\HutangDagangResource;
use App\Models\HutangDagang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HutangDagangController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = HutangDagang::with('supplier');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        return HutangDagangResource::collection($query->latest()->paginate(15));
    }

    public function bayar(int $id, BayarHutangRequest $request, BayarHutangSupplierAction $action): JsonResponse
    {
        try {
            $hutang = $action->execute($id, (int) $request->validated()['jumlah_bayar']);
            return (new HutangDagangResource($hutang))->response()->setStatusCode(200);
        } catch (PesananTidakValidException $e) {
            return response()->json([
                'type' => 'https://tools.ietf.org/html/rfc9457',
                'title' => 'Pembayaran Tidak Valid',
                'status' => 400,
                'detail' => $e->getMessage(),
            ], 400);
        }
    }
}
