<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TransaksiKasir extends Model
{
    protected $table = 'transaksi_kasir';

    protected $fillable = [
        'nomor_transaksi',
        'tanggal_transaksi',
        'total_belanja',
        'metode_bayar',
        'status_bayar',
        'kasir_nama',
        'catatan',
    ];

    protected $casts = [
        'tanggal_transaksi' => 'datetime',
        'total_belanja' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ItemTransaksiKasir::class, 'transaksi_kasir_id');
    }

    public function pembayaran(): MorphOne
    {
        return $this->morphOne(Pembayaran::class, 'payable');
    }
}
