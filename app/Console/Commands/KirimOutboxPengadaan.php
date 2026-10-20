<?php

namespace App\Console\Commands;

use App\Messaging\PenerbitPesanan;
use App\Models\OutboxKirim;
use Illuminate\Console\Command;
use Throwable;

class KirimOutboxPengadaan extends Command
{
    protected $signature = 'pengadaan:kirim-outbox {--limit=50 : Jumlah event maksimal per batch}';
    protected $description = 'Kirim seluruh event tertunda di tabel outbox_kirim ke RabbitMQ (Outbox Pattern)';

    public function handle(PenerbitPesanan $penerbit): int
    {
        $limit = (int) $this->option('limit');
        $events = OutboxKirim::where('status', 'pending')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        if ($events->isEmpty()) {
            $this->info('Tidak ada pesan outbox yang berstatus pending.');
            return Command::SUCCESS;
        }

        $this->info("Menemukan {$events->count()} pesan outbox untuk dikirim...");

        $sukses = 0;
        $gagal = 0;

        foreach ($events as $outbox) {
            try {
                $penerbit->publish(
                    routingKey: $outbox->routing_key,
                    data: $outbox->payload,
                    messageId: $outbox->event_id,
                    type: $outbox->event_type
                );

                $outbox->update([
                    'status' => 'terkirim',
                    'sent_at' => now(),
                ]);

                $this->line("  [OK] Event {$outbox->event_type} [{$outbox->event_id}] -> {$outbox->routing_key}");
                $sukses++;
            } catch (Throwable $e) {
                $outbox->increment('retry_count');
                if ($outbox->retry_count >= 5) {
                    $outbox->update(['status' => 'gagal']);
                }

                $this->error("  [FAIL] Event {$outbox->event_type} [{$outbox->event_id}]: {$e->getMessage()}");
                $gagal++;
            }
        }

        $this->info("Selesai diproses: Sukses: {$sukses}, Gagal: {$gagal}");
        return $gagal === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
