<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPesananWa extends Model
{
    protected $table = 'item_pesanan_wa';

    protected $fillable = [
        'pesanan_wa_id',
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

    public function pesananWa(): BelongsTo
    {
        return $this->belongsTo(PesananWa::class, 'pesanan_wa_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
