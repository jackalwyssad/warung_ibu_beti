<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BayarHutangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jumlah_bayar' => ['required', 'integer', 'min:1'],
        ];
    }
}
