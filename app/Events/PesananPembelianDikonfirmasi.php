<?php

namespace App\Events;

class PesananPembelianDikonfirmasi
{
    public function __construct(
        public readonly int $pesananId,
        public readonly string $nomorPo,
        public readonly string $kodeSupplier,
        public readonly string $namaSupplier,
        public readonly int $totalNilai,
        public readonly string $metodeBayar,
        public readonly array $items,
        public readonly string $tanggalPesan,
        public readonly ?string $tanggalEstimasi = null
    ) {}

    public function toArray(): array
    {
        return [
            'pesanan_id' => $this->pesananId,
            'nomor_po' => $this->nomorPo,
            'kode_supplier' => $this->kodeSupplier,
            'nama_supplier' => $this->namaSupplier,
            'total_nilai' => $this->totalNilai,
            'metode_bayar' => $this->metodeBayar,
            'items' => $this->items,
            'tanggal_pesan' => $this->tanggalPesan,
            'tanggal_estimasi' => $this->tanggalEstimasi,
            'waktu_event' => now()->toIso8601String(),
        ];
    }
}
