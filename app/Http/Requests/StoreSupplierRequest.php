<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode_supplier' => ['required', 'string', 'max:30', 'unique:supplier,kode_supplier'],
            'nama' => ['required', 'string', 'max:100'],
            'kontak' => ['required', 'string', 'max:50'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'limit_kredit' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
