<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePesananRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:supplier,id'],
            'metode_bayar' => ['required', 'in:tempo,tunai'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'tanggal_pesan' => ['required', 'date'],
            'tanggal_estimasi' => ['nullable', 'date', 'after_or_equal:tanggal_pesan'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['required', 'string', 'max:40'],
            'items.*.nama_barang' => ['required', 'string', 'max:120'],
            'items.*.jumlah_pesan' => ['required', 'integer', 'min:1'],
            'items.*.harga_satuan' => ['required', 'integer', 'min:1'],
        ];
    }
}
