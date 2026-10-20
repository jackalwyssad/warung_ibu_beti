<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barang extends Model
{
    protected $table = 'barang';

    protected $fillable = [
        'sku',
        'nama_barang',
        'kategori',
        'stok',
        'harga_beli',
        'harga_jual',
        'satuan',
    ];

    protected $casts = [
        'stok' => 'integer',
        'harga_beli' => 'integer',
        'harga_jual' => 'integer',
    ];

    public function itemTransaksi(): HasMany
    {
        return $this->hasMany(ItemTransaksiKasir::class, 'barang_id');
    }

    public function itemPesananWa(): HasMany
    {
        return $this->hasMany(ItemPesananWa::class, 'barang_id');
    }
}
