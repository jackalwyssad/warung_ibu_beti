<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\PesananWa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotWhatsAppMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_whatsapp_dapat_menerbitkan_pesanan_ke_antrean_messaging(): void
    {
        $barang = Barang::create([
            'sku' => 'TELUR-1KG',
            'nama_barang' => 'Telur Ayam 1kg',
            'stok' => 50,
            'harga_jual' => 28000,
        ]);

        $response = $this->postJson('/api/v1/wa/pesanan', [
            'nomor_telepon' => '0812345678',
            'nama_pelanggan' => 'Ibu Rina',
            'alamat_antar' => 'Gang Melati 2',
            'metode_bayar' => 'tunai',
            'items' => [
                ['sku' => 'TELUR-1KG', 'qty' => 2],
            ],
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_nilai', 56000);

        $this->assertDatabaseHas('pesanan_wa', [
            'nomor_telepon' => '0812345678',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('outbox_kirim', [
            'event_type' => 'PesananWhatsAppMasuk',
            'routing_key' => 'wa.pesanan.masuk',
            'status' => 'pending',
        ]);
    }
}
