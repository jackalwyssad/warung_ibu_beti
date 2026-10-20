<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class PesananWa extends Model
{
    protected $table = 'pesanan_wa';

    protected $fillable = [
        'nomor_pesanan_wa',
        'nomor_telepon',
        'nama_pelanggan',
        'alamat_antar',
        'status',
        'total_nilai',
        'metode_bayar',
    ];

    protected $casts = [
        'total_nilai' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ItemPesananWa::class, 'pesanan_wa_id');
    }

    public function pembayaran(): MorphOne
    {
        return $this->morphOne(Pembayaran::class, 'payable');
    }
}
