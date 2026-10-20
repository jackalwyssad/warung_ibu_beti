<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\KonfirmasiPesananAction;
use App\Exceptions\KreditSupplierTerlampaui;
use App\Exceptions\PesananTidakValidException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePesananRequest;
use App\Http\Resources\PesananPembelianResource;
use App\Models\ItemPesananPembelian;
use App\Models\PesananPembelian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PesananPembelianController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PesananPembelian::with(['supplier', 'items']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        return PesananPembelianResource::collection($query->latest()->paginate(15));
    }

    public function store(StorePesananRequest $request): JsonResponse
    {
        $data = $request->validated();

        $pesanan = DB::transaction(function () use ($data) {
            $nomorPo = 'PO-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            $totalNilai = 0;
            foreach ($data['items'] as $item) {
                $totalNilai += ($item['jumlah_pesan'] * $item['harga_satuan']);
            }

            $po = PesananPembelian::create([
                'nomor_po' => $nomorPo,
                'supplier_id' => $data['supplier_id'],
                'status' => 'draft',
                'total_nilai' => $totalNilai,
                'metode_bayar' => $data['metode_bayar'],
                'keterangan' => $data['keterangan'] ?? null,
                'tanggal_pesan' => $data['tanggal_pesan'],
                'tanggal_estimasi' => $data['tanggal_estimasi'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                ItemPesananPembelian::create([
                    'pesanan_pembelian_id' => $po->id,
                    'sku' => $item['sku'],
                    'nama_barang' => $item['nama_barang'],
                    'jumlah_pesan' => $item['jumlah_pesan'],
                    'jumlah_diterima' => 0,
                    'harga_satuan' => $item['harga_satuan'],
                    'subtotal' => $item['jumlah_pesan'] * $item['harga_satuan'],
                ]);
            }

            return $po->load(['supplier', 'items']);
        });

        return (new PesananPembelianResource($pesanan))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id): PesananPembelianResource
    {
        $po = PesananPembelian::with(['supplier', 'items', 'penerimaan'])->findOrFail($id);
        return new PesananPembelianResource($po);
    }

    public function konfirmasi(int $id, KonfirmasiPesananAction $action): JsonResponse
    {
        try {
            $po = $action->execute($id);
            return (new PesananPembelianResource($po))->response()->setStatusCode(200);
        } catch (KreditSupplierTerlampaui $e) {
            return response()->json([
                'type' => 'https://tools.ietf.org/html/rfc9457',
                'title' => 'Limit Kredit Supplier Terlampaui',
                'status' => 422,
                'detail' => $e->getMessage(),
                'kode_supplier' => $e->kodeSupplier,
            ], 422);
        } catch (PesananTidakValidException $e) {
            return response()->json([
                'type' => 'https://tools.ietf.org/html/rfc9457',
                'title' => 'Pesanan Tidak Valid',
                'status' => 400,
                'detail' => $e->getMessage(),
            ], 400);
        }
    }
}
