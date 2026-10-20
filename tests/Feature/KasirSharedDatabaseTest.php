<?php

namespace Tests\Feature;

use App\Models\Barang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasirSharedDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaksi_kasir_langsung_memotong_stok_di_shared_database(): void
    {
        $barang = Barang::create([
            'sku' => 'BRS-01',
            'nama_barang' => 'Beras 5kg',
            'kategori' => 'sembako',
            'stok' => 20,
            'harga_beli' => 60000,
            'harga_jual' => 70000,
            'satuan' => 'karung',
        ]);

        $response = $this->postJson('/api/v1/kasir/transaksi', [
            'kasir_nama' => 'Kasir 1',
            'metode_bayar' => 'tunai',
            'uang_diterima' => 150000,
            'items' => [
                ['sku' => 'BRS-01', 'qty' => 2],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_belanja', 140000)
            ->assertJsonPath('data.pembayaran.kembalian', 10000);

        // Verifikasi stok langsung terpotong di DB bersama (Shared DB)
        $this->assertEquals(18, $barang->fresh()->stok);
    }

    public function test_menolak_transaksi_kasir_jika_stok_kurang(): void
    {
        Barang::create([
            'sku' => 'MYK-01',
            'nama_barang' => 'Minyak 1L',
            'stok' => 3,
            'harga_jual' => 20000,
        ]);

        $response = $this->postJson('/api/v1/kasir/transaksi', [
            'metode_bayar' => 'tunai',
            'items' => [
                ['sku' => 'MYK-01', 'qty' => 10], // minta 10 padahal stok 3
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('title', 'Stok Tidak Mencukupi');
    }
}
