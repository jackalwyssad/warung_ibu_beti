<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HutangDagang extends Model
{
    protected $table = 'hutang_dagang';

    protected $fillable = [
        'supplier_id',
        'pesanan_pembelian_id',
        'nomor_faktur',
        'jumlah_hutang',
        'jumlah_dibayar',
        'sisa_hutang',
        'jatuh_tempo',
        'status',
    ];

    protected $casts = [
        'jumlah_hutang' => 'integer',
        'jumlah_dibayar' => 'integer',
        'sisa_hutang' => 'integer',
        'jatuh_tempo' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function pesananPembelian(): BelongsTo
    {
        return $this->belongsTo(PesananPembelian::class, 'pesanan_pembelian_id');
    }
}
