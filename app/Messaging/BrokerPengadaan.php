<?php

namespace App\Messaging;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

class BrokerPengadaan
{
    public static function koneksi(): AMQPStreamConnection
    {
        $c = config('rabbitmq');
        return new AMQPStreamConnection(
            $c['host'] ?? 'rabbitmq',
            (int) ($c['port'] ?? 5672),
            $c['user'] ?? 'beti',
            $c['password'] ?? 'rahasia123',
            $c['vhost'] ?? '/'
        );
    }

    /**
     * Menyiapkan topologi RabbitMQ:
     * 1. Topic Exchange Utama: warung.pengadaan.events
     * 2. Antrean Logistik Gudang (gudang.masuk)
     * 3. Antrean Buku Hutang (keuangan.buku-hutang)
     * 4. Antrean Bot WhatsApp (wa.pesanan-antrean) -> Gaya Messaging Tugas 3
     * 5. Fanout Dead Letter Exchange (DLX) & Queue DLQ
     */
    public static function siapkanTopologi(AMQPChannel $channel): void
    {
        $exchangeUtama = 'warung.pengadaan.events';
        $exchangeDlx = 'warung.pengadaan.dlx';
        $queueDlq = 'pengadaan.audit.dlq';

        // 1. Declare Topic Exchange Utama
        $channel->exchange_declare($exchangeUtama, 'topic', false, true, false);

        // 2. Declare Fanout DLX dan Dead Letter Queue untuk pesan bermasalah
        $channel->exchange_declare($exchangeDlx, 'fanout', false, true, false);
        $channel->queue_declare($queueDlq, false, true, false, false);
        $channel->queue_bind($queueDlq, $exchangeDlx);

        // 3. Antrean Gudang: Menerima konfirmasi PO & penerimaan barang fisik
        $channel->queue_declare(
            'gudang.masuk',
            false,
            true,
            false,
            false,
            false,
            new AMQPTable(['x-dead-letter-exchange' => $exchangeDlx])
        );
        $channel->queue_bind('gudang.masuk', $exchangeUtama, 'pengadaan.po.*');
        $channel->queue_bind('gudang.masuk', $exchangeUtama, 'pengadaan.barang.*');

        // 4. Antrean Keuangan / Buku Hutang: Menerima penerbitan PO kredit dan pelunasan faktur
        $channel->queue_declare(
            'keuangan.buku-hutang',
            false,
            true,
            false,
            false,
            false,
            new AMQPTable(['x-dead-letter-exchange' => $exchangeDlx])
        );
        $channel->queue_bind('keuangan.buku-hutang', $exchangeUtama, 'pengadaan.po.dikonfirmasi');
        $channel->queue_bind('keuangan.buku-hutang', $exchangeUtama, 'pengadaan.hutang.*');

        // 5. Antrean Bot WhatsApp (Gaya Messaging - Sesuai Tugas 3)
        $channel->queue_declare(
            'wa.pesanan-antrean',
            false,
            true,
            false,
            false,
            false,
            new AMQPTable(['x-dead-letter-exchange' => $exchangeDlx])
        );
        $channel->queue_bind('wa.pesanan-antrean', $exchangeUtama, 'wa.pesanan.*');
    }
}
