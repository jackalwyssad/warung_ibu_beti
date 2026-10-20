<?php

namespace Tests\Feature;

use App\Models\ItemPesananPembelian;
use App\Models\OutboxKirim;
use App\Models\PesananPembelian;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PesananPembelianApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dapat_membuat_draft_purchase_order(): void
    {
        $supplier = Supplier::create([
            'kode_supplier' => 'SUP-002',
            'nama' => 'CV Makmur Sejahtera',
            'kontak' => '08222',
            'limit_kredit' => 10000000,
        ]);

        $payload = [
            'supplier_id' => $supplier->id,
            'metode_bayar' => 'tempo',
            'keterangan' => 'Pengadaan stok gula pasir',
            'tanggal_pesan' => now()->toDateString(),
            'items' => [
                [
                    'sku' => 'GLA-PSR-1KG',
                    'nama_barang' => 'Gula Pasir Kristal 1kg',
                    'jumlah_pesan' => 100,
                    'harga_satuan' => 16000,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/pesanan', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_nilai', 1600000)
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('pesanan_pembelian', [
            'supplier_id' => $supplier->id,
            'total_nilai' => 1600000,
        ]);
    }

    public function test_konfirmasi_po_menulis_ke_outbox_pattern(): void
    {
        $supplier = Supplier::create([
            'kode_supplier' => 'SUP-003',
            'nama' => 'PT Beras Bandung',
            'kontak' => '08333',
            'total_hutang' => 0,
            'limit_kredit' => 5000000,
        ]);

        $po = PesananPembelian::create([
            'nomor_po' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'total_nilai' => 2000000,
            'metode_bayar' => 'tempo',
            'tanggal_pesan' => now()->toDateString(),
        ]);

        ItemPesananPembelian::create([
            'pesanan_pembelian_id' => $po->id,
            'sku' => 'BRS-01',
            'nama_barang' => 'Beras Cianjur 5kg',
            'jumlah_pesan' => 20,
            'jumlah_diterima' => 0,
            'harga_satuan' => 100000,
            'subtotal' => 2000000,
        ]);

        $response = $this->postJson("/api/v1/pesanan/{$po->id}/konfirmasi");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'dikonfirmasi');

        $this->assertDatabaseHas('outbox_kirim', [
            'event_type' => 'PesananPembelianDikonfirmasi',
            'routing_key' => 'pengadaan.po.dikonfirmasi',
            'status' => 'pending',
        ]);
    }

    public function test_menolak_konfirmasi_jika_limit_kredit_terlampaui(): void
    {
        $supplier = Supplier::create([
            'kode_supplier' => 'SUP-LIMIT',
            'nama' => 'PT Distributor Terbatas',
            'kontak' => '08444',
            'total_hutang' => 9000000,
            'limit_kredit' => 10000000, // Sisa kredit hanya Rp1.000.000
        ]);

        $po = PesananPembelian::create([
            'nomor_po' => 'PO-TEST-LIMIT',
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'total_nilai' => 3000000, // Rp3.000.000 melebihi sisa Rp1.000.000
            'metode_bayar' => 'tempo',
            'tanggal_pesan' => now()->toDateString(),
        ]);

        ItemPesananPembelian::create([
            'pesanan_pembelian_id' => $po->id,
            'sku' => 'MNY-01',
            'nama_barang' => 'Minyak 2L',
            'jumlah_pesan' => 75,
            'jumlah_diterima' => 0,
            'harga_satuan' => 40000,
            'subtotal' => 3000000,
        ]);

        $response = $this->postJson("/api/v1/pesanan/{$po->id}/konfirmasi");

        $response->assertStatus(422)
            ->assertJsonPath('title', 'Limit Kredit Supplier Terlampaui');
    }
}
