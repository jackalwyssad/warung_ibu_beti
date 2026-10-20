<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PesananPembelianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nomor_po' => $this->nomor_po,
            'supplier' => [
                'id' => $this->supplier?->id,
                'kode_supplier' => $this->supplier?->kode_supplier,
                'nama' => $this->supplier?->nama,
            ],
            'status' => $this->status,
            'total_nilai' => $this->total_nilai,
            'metode_bayar' => $this->metode_bayar,
            'keterangan' => $this->keterangan,
            'tanggal_pesan' => $this->tanggal_pesan?->format('Y-m-d'),
            'tanggal_estimasi' => $this->tanggal_estimasi?->format('Y-m-d'),
            'items' => $this->items->map(fn($it) => [
                'id' => $it->id,
                'sku' => $it->sku,
                'nama_barang' => $it->nama_barang,
                'jumlah_pesan' => $it->jumlah_pesan,
                'jumlah_diterima' => $it->jumlah_diterima,
                'harga_satuan' => $it->harga_satuan,
                'subtotal' => $it->subtotal,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
