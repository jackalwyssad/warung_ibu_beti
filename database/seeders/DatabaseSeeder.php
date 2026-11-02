<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Barang;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Katalog Sembako Master Awal
        Barang::updateOrCreate(
            ['sku' => 'BRS-PREM-5KG'],
            [
                'nama_barang' => 'Beras Pandan Wangi 5kg',
                'kategori' => 'sembako',
                'stok' => 50,
                'harga_beli' => 65000,
                'harga_jual' => 75000,
                'satuan' => 'karung',
            ]
        );

        Barang::updateOrCreate(
            ['sku' => 'MYK-GRG-2L'],
            [
                'nama_barang' => 'Minyak Goreng Sawit 2L',
                'kategori' => 'sembako',
                'stok' => 40,
                'harga_beli' => 33000,
                'harga_jual' => 38000,
                'satuan' => 'pouch',
            ]
        );
    }
}
