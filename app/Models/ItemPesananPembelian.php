<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPesananPembelian extends Model
{
    protected $table = 'item_pesanan_pembelian';

    protected $fillable = [
        'pesanan_pembelian_id',
        'sku',
        'nama_barang',
        'jumlah_pesan',
        'jumlah_diterima',
        'harga_satuan',
        'subtotal',
    ];

    protected $casts = [
        'jumlah_pesan' => 'integer',
        'jumlah_diterima' => 'integer',
        'harga_satuan' => 'integer',
        'subtotal' => 'integer',
    ];

    public function pesananPembelian(): BelongsTo
    {
        return $this->belongsTo(PesananPembelian::class, 'pesanan_pembelian_id');
    }
}
