<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dapat_membuat_supplier_baru(): void
    {
        $response = $this->postJson('/api/v1/suppliers', [
            'kode_supplier' => 'SUP-001',
            'nama' => 'PT Sumber Sembako Utama',
            'kontak' => '081234567890',
            'alamat' => 'Jl. Cibaduyut No. 12',
            'limit_kredit' => 20000000,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.kode_supplier', 'SUP-001')
            ->assertJsonPath('data.nama', 'PT Sumber Sembako Utama')
            ->assertJsonPath('data.sisa_kredit', 20000000);

        $this->assertDatabaseHas('supplier', [
            'kode_supplier' => 'SUP-001',
        ]);
    }

    public function test_dapat_mengambil_daftar_supplier(): void
    {
        Supplier::create([
            'kode_supplier' => 'SUP-A',
            'nama' => 'Distributor A',
            'kontak' => '08111',
            'total_hutang' => 0,
            'limit_kredit' => 10000000,
        ]);

        $response = $this->getJson('/api/v1/suppliers');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }
}
