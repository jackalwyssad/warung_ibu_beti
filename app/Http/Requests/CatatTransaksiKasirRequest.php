<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatatTransaksiKasirRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'metode_bayar' => ['required', 'in:tunai,qris'],
            'uang_diterima' => ['nullable', 'integer', 'min:0'],
            'kasir_nama' => ['nullable', 'string', 'max:80'],
            'catatan' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['required', 'string', 'exists:barang,sku'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }
}
