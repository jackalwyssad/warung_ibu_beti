<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Pembayaran extends Model
{
    protected $table = 'pembayaran';

    protected $fillable = [
        'nomor_referensi',
        'payable_id',
        'payable_type',
        'metode',
        'total_tagihan',
        'uang_diterima',
        'kembalian',
        'qr_string',
        'status',
    ];

    protected $casts = [
        'total_tagihan' => 'integer',
        'uang_diterima' => 'integer',
        'kembalian' => 'integer',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
