<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KirimPesananWaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nomor_telepon' => ['required', 'string', 'max:30'],
            'nama_pelanggan' => ['required', 'string', 'max:100'],
            'alamat_antar' => ['nullable', 'string', 'max:255'],
            'metode_bayar' => ['nullable', 'in:tunai,qris'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['required', 'string', 'exists:barang,sku'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }
}
