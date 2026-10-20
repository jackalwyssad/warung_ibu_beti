<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenerimaanBarang extends Model
{
    protected $table = 'penerimaan_barang';

    protected $fillable = [
        'nomor_penerimaan',
        'pesanan_pembelian_id',
        'tanggal_terima',
        'penerima',
        'catatan',
        'status',
    ];

    protected $casts = [
        'tanggal_terima' => 'datetime',
    ];

    public function pesananPembelian(): BelongsTo
    {
        return $this->belongsTo(PesananPembelian::class, 'pesanan_pembelian_id');
    }
}
