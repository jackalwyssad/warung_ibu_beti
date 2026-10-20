<?php

namespace Tests\Feature;

use App\Models\ItemPesananPembelian;
use App\Models\PesananPembelian;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenerimaanBarangApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dapat_mencatat_penerimaan_barang_dan_menerbitkan_hutang(): void
    {
        $supplier = Supplier::create([
            'kode_supplier' => 'SUP-RCV',
            'nama' => 'Supplier Logistik',
            'kontak' => '08555',
            'total_hutang' => 0,
            'limit_kredit' => 10000000,
        ]);

        $po = PesananPembelian::create([
            'nomor_po' => 'PO-RCV-001',
            'supplier_id' => $supplier->id,
            'status' => 'dikonfirmasi',
            'total_nilai' => 1000000,
            'metode_bayar' => 'tempo',
            'tanggal_pesan' => now()->toDateString(),
        ]);

        ItemPesananPembelian::create([
            'pesanan_pembelian_id' => $po->id,
            'sku' => 'TLR-1KG',
            'nama_barang' => 'Telur Ayam Ras 1kg',
            'jumlah_pesan' => 40,
            'jumlah_diterima' => 0,
            'harga_satuan' => 25000,
            'subtotal' => 1000000,
        ]);

        $response = $this->postJson("/api/v1/pesanan/{$po->id}/terima-barang", [
            'penerima' => 'Petugas Gudang',
            'catatan' => 'Semua telur utuh',
            'items' => [
                ['sku' => 'TLR-1KG', 'jumlah_diterima' => 40],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'lengkap');

        $this->assertDatabaseHas('hutang_dagang', [
            'supplier_id' => $supplier->id,
            'jumlah_hutang' => 1000000,
            'status' => 'belum_lunas',
        ]);

        $this->assertEquals(1000000, $supplier->fresh()->total_hutang);
    }
}
