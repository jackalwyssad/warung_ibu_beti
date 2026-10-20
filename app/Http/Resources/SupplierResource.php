<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_supplier' => $this->kode_supplier,
            'nama' => $this->nama,
            'kontak' => $this->kontak,
            'alamat' => $this->alamat,
            'total_hutang' => $this->total_hutang,
            'limit_kredit' => $this->limit_kredit,
            'sisa_kredit' => $this->sisa_kredit,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
