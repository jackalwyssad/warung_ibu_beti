<?php

namespace Tests\Feature;

use App\Models\OutboxKirim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OutboxMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_kirim_outbox_berjalan_tanpa_error(): void
    {
        OutboxKirim::create([
            'event_id' => (string) \Illuminate\Support\Str::uuid(),
            'event_type' => 'TestEvent',
            'routing_key' => 'pengadaan.test',
            'payload' => ['keterangan' => 'Uji Outbox'],
            'status' => 'pending',
        ]);

        $this->assertEquals(1, OutboxKirim::where('status', 'pending')->count());
    }
}
