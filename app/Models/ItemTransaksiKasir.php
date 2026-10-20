<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemTransaksiKasir extends Model
{
    protected $table = 'item_transaksi_kasir';

    protected $fillable = [
        'transaksi_kasir_id',
        'barang_id',
        'sku',
        'nama_barang',
        'qty',
        'harga_satuan',
        'subtotal',
    ];

    protected $casts = [
        'qty' => 'integer',
        'harga_satuan' => 'integer',
        'subtotal' => 'integer',
    ];

    public function transaksiKasir(): BelongsTo
    {
        return $this->belongsTo(TransaksiKasir::class, 'transaksi_kasir_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
