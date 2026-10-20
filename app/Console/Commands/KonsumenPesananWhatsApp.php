<?php

namespace App\Console\Commands;

use App\Messaging\BrokerPengadaan;
use App\Models\Barang;
use App\Models\PesananWa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpAmqpLib\Message\AMQPMessage;

class KonsumenPesananWhatsApp extends Command
{
    protected $signature = 'wa:proses-pesanan {--timeout=10 : Batas waktu tunggu pesan (detik)}';
    protected $description = 'Consumer antrean wa.pesanan-antrean: memproses pesanan online WhatsApp secara asinkron (reservasi stok & konfirmasi)';

    public function handle(): int
    {
        $this->info('Memulai Consumer Bot WhatsApp (antrean: wa.pesanan-antrean)...');

        $koneksi = BrokerPengadaan::koneksi();
        $channel = $koneksi->channel();
        BrokerPengadaan::siapkanTopologi($channel);

        $prosesPesan = function (AMQPMessage $msg) {
            $data = json_decode($msg->body, true);
            $type = $msg->get('type');
            $routingKey = $msg->getRoutingKey();

            $this->info("--------------------------------------------------");
            $this->info("[BOT WHATSAPP WORKER] Menerima event: {$type} (Key: {$routingKey})");

            $pesananWa = PesananWa::find($data['pesanan_wa_id']);
            if (!$pesananWa) {
                $this->error("Pesanan WA ID {$data['pesanan_wa_id']} tidak ditemukan.");
                $msg->ack();
                return;
            }

            // Eksekusi reservasi stok secara asinkron di belakang layar
            DB::transaction(function () use ($pesananWa, $data) {
                $stokCukup = true;
                foreach ($data['items'] as $it) {
                    $barang = Barang::where('sku', $it['sku'])->lockForUpdate()->first();
                    if (!$barang || $barang->stok < $it['qty']) {
                        $stokCukup = false;
                        break;
                    }
                }

                if ($stokCukup) {
                    foreach ($data['items'] as $it) {
                        Barang::where('sku', $it['sku'])->decrement('stok', $it['qty']);
                    }
                    $pesananWa->status = 'diproses';
                    $pesananWa->save();
                    $this->line("  [SUKSES] Pesanan WA {$pesananWa->nomor_pesanan_wa} berhasil direservasi stok & diubah ke status 'diproses'.");
                } else {
                    $pesananWa->status = 'dibatalkan';
                    $pesananWa->save();
                    $this->warn("  [BATAL] Stok tidak mencukupi untuk pesanan WA {$pesananWa->nomor_pesanan_wa}.");
                }
            });

            $msg->ack();
        };

        $channel->basic_consume('wa.pesanan-antrean', '', false, false, false, false, $prosesPesan);

        $timeout = (int) $this->option('timeout');
        $mulai = time();

        while ($channel->is_consuming() && (time() - $mulai) < $timeout) {
            try {
                $channel->wait(null, false, 1);
            } catch (\PhpAmqpLib\Exception\AMQPTimeoutException) {
                // Timeout per detik
            }
        }

        $channel->close();
        $koneksi->close();

        $this->info('Consumer Bot WhatsApp selesai.');
        return Command::SUCCESS;
    }
}
