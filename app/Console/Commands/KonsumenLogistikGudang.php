<?php

namespace App\Console\Commands;

use App\Messaging\BrokerPengadaan;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;

class KonsumenLogistikGudang extends Command
{
    protected $signature = 'pengadaan:konsumen-logistik {--timeout=10 : Batas waktu tunggu pesan (detik)}';
    protected $description = 'Consumer antrean gudang.masuk: mencatat kedatangan barang dan pembaruan stok gudang';

    public function handle(): int
    {
        $this->info('Memulai Consumer Logistik Gudang (antrean: gudang.masuk)...');

        $koneksi = BrokerPengadaan::koneksi();
        $channel = $koneksi->channel();
        BrokerPengadaan::siapkanTopologi($channel);

        $prosesPesan = function (AMQPMessage $msg) {
            $data = json_decode($msg->body, true);
            $type = $msg->get('type');
            $routingKey = $msg->getRoutingKey();

            $this->info("--------------------------------------------------");
            $this->info("[LOGISTIK GUDANG] Menerima event: {$type} (Key: {$routingKey})");

            if ($type === 'PesananPembelianDikonfirmasi') {
                $this->line("  PO Baru Masuk: {$data['nomor_po']} dari {$data['nama_supplier']}");
                $this->line("  Estimasi Datang: " . ($data['tanggal_estimasi'] ?? 'Belum ditentukan'));
                foreach ($data['items'] as $it) {
                    $this->line("   - {$it['sku']}: {$it['nama_barang']} ({$it['jumlah_pesan']} pcs)");
                }
            } elseif ($type === 'BarangDiterimaSupplier') {
                $this->line("  Penerimaan Fisik: {$data['nomor_penerimaan']} untuk {$data['nomor_po']}");
                $this->line("  Diterima oleh: {$data['penerima']} | Status: {$data['status_penerimaan']}");
                foreach ($data['rincian_barang'] as $it) {
                    $this->line("   - {$it['sku']}: +{$it['jumlah_diterima']} pcs diterima (Total: {$it['total_diterima']}/{$it['jumlah_pesan']})");
                }
            }

            $msg->ack();
        };

        $channel->basic_consume('gudang.masuk', '', false, false, false, false, $prosesPesan);

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

        $this->info('Consumer Logistik Gudang selesai.');
        return Command::SUCCESS;
    }
}
