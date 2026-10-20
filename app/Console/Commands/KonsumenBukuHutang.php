<?php

namespace App\Console\Commands;

use App\Messaging\BrokerPengadaan;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;

class KonsumenBukuHutang extends Command
{
    protected $signature = 'pengadaan:konsumen-hutang {--timeout=10 : Batas waktu tunggu pesan (detik)}';
    protected $description = 'Consumer antrean keuangan.buku-hutang: mencatat hutang dagang supplier dan pelunasan kas';

    public function handle(): int
    {
        $this->info('Memulai Consumer Buku Hutang (antrean: keuangan.buku-hutang)...');

        $koneksi = BrokerPengadaan::koneksi();
        $channel = $koneksi->channel();
        BrokerPengadaan::siapkanTopologi($channel);

        $prosesPesan = function (AMQPMessage $msg) {
            $data = json_decode($msg->body, true);
            $type = $msg->get('type');
            $routingKey = $msg->getRoutingKey();

            $this->info("==================================================");
            $this->info("[KEUANGAN BUKU HUTANG] Menerima event: {$type} (Key: {$routingKey})");

            if ($type === 'PesananPembelianDikonfirmasi') {
                $this->line("  Komitmen PO: {$data['nomor_po']} ({$data['nama_supplier']})");
                $this->line("  Metode Bayar: {$data['metode_bayar']} | Nilai: Rp" . number_format($data['total_nilai'], 0, ',', '.'));
            } elseif ($type === 'PembayaranHutangDicatat') {
                $this->line("  Pelunasan Faktur: {$data['nomor_faktur']} ({$data['nama_supplier']})");
                $this->line("  Jumlah Dibayar: Rp" . number_format($data['jumlah_bayar'], 0, ',', '.'));
                $this->line("  Sisa Hutang: Rp" . number_format($data['sisa_hutang'], 0, ',', '.') . " [{$data['status_hutang']}]");
            }

            $msg->ack();
        };

        $channel->basic_consume('keuangan.buku-hutang', '', false, false, false, false, $prosesPesan);

        $timeout = (int) $this->option('timeout');
        $mulai = time();

        while ($channel->is_consuming() && (time() - $mulai) < $timeout) {
            try {
                $channel->wait(null, false, 1);
            } catch (\PhpAmqpLib\Exception\AMQPTimeoutException) {
                // Timeout per detik biasa
            }
        }

        $channel->close();
        $koneksi->close();

        $this->info('Consumer Buku Hutang selesai.');
        return Command::SUCCESS;
    }
}
