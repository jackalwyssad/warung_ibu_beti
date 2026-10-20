<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HutangDagangResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nomor_faktur' => $this->nomor_faktur,
            'supplier' => [
                'id' => $this->supplier?->id,
                'kode_supplier' => $this->supplier?->kode_supplier,
                'nama' => $this->supplier?->nama,
            ],
            'jumlah_hutang' => $this->jumlah_hutang,
            'jumlah_dibayar' => $this->jumlah_dibayar,
            'sisa_hutang' => $this->sisa_hutang,
            'jatuh_tempo' => $this->jatuh_tempo?->format('Y-m-d'),
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
