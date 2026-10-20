<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $table = 'supplier';

    protected $fillable = [
        'kode_supplier',
        'nama',
        'kontak',
        'alamat',
        'total_hutang',
        'limit_kredit',
    ];

    protected $casts = [
        'total_hutang' => 'integer',
        'limit_kredit' => 'integer',
    ];

    public function pesananPembelian(): HasMany
    {
        return $this->hasMany(PesananPembelian::class, 'supplier_id');
    }

    public function hutangDagang(): HasMany
    {
        return $this->hasMany(HutangDagang::class, 'supplier_id');
    }

    public function getSisaKreditAttribute(): int
    {
        return max(0, $this->limit_kredit - $this->total_hutang);
    }
}
