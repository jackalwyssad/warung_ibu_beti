<?php

namespace App\Messaging;

use PhpAmqpLib\Message\AMQPMessage;

class PenerbitPesanan
{
    public function publish(string $routingKey, array $data, string $messageId, string $type): void
    {
        $koneksi = BrokerPengadaan::koneksi();
        $channel = $koneksi->channel();

        try {
            BrokerPengadaan::siapkanTopologi($channel);

            $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $msg = new AMQPMessage($body, [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $messageId,
                'type' => $type,
                'timestamp' => time(),
            ]);

            $channel->basic_publish($msg, 'warung.pengadaan.events', $routingKey);
        } finally {
            $channel->close();
            $koneksi->close();
        }
    }
}
