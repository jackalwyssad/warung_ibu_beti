<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PesananPembelian extends Model
{
    protected $table = 'pesanan_pembelian';

    protected $fillable = [
        'nomor_po',
        'supplier_id',
        'status',
        'total_nilai',
        'metode_bayar',
        'keterangan',
        'tanggal_pesan',
        'tanggal_estimasi',
    ];

    protected $casts = [
        'total_nilai' => 'integer',
        'tanggal_pesan' => 'date',
        'tanggal_estimasi' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemPesananPembelian::class, 'pesanan_pembelian_id');
    }

    public function penerimaan(): HasMany
    {
        return $this->hasMany(PenerimaanBarang::class, 'pesanan_pembelian_id');
    }
}
