<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarangController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Barang::query();

        if ($kategori = $request->query('kategori')) {
            $query->where('kategori', $kategori);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_barang', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest()->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:40', 'unique:barang,sku'],
            'nama_barang' => ['required', 'string', 'max:120'],
            'kategori' => ['nullable', 'string', 'max:50'],
            'stok' => ['required', 'integer', 'min:0'],
            'harga_beli' => ['required', 'integer', 'min:0'],
            'harga_jual' => ['required', 'integer', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:20'],
        ]);

        $barang = Barang::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Barang berhasil ditambahkan ke inventori.',
            'data' => $barang,
        ], 201);
    }

    public function show(string $sku): JsonResponse
    {
        $barang = Barang::where('sku', $sku)->firstOrFail();
        return response()->json([
            'status' => 'success',
            'data' => $barang,
        ]);
    }
}
