<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PenerimaanBarangResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nomor_penerimaan' => $this->nomor_penerimaan,
            'pesanan_pembelian_id' => $this->pesanan_pembelian_id,
            'tanggal_terima' => $this->tanggal_terima?->toIso8601String(),
            'penerima' => $this->penerima,
            'catatan' => $this->catatan,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
