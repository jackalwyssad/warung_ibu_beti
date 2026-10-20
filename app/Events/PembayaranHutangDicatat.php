<?php

namespace App\Events;

class PembayaranHutangDicatat
{
    public function __construct(
        public readonly int $hutangId,
        public readonly string $nomorFaktur,
        public readonly string $kodeSupplier,
        public readonly string $namaSupplier,
        public readonly int $jumlahBayar,
        public readonly int $sisaHutang,
        public readonly string $statusHutang,
        public readonly string $tanggalBayar
    ) {}

    public function toArray(): array
    {
        return [
            'hutang_id' => $this->hutangId,
            'nomor_faktur' => $this->nomorFaktur,
            'kode_supplier' => $this->kodeSupplier,
            'nama_supplier' => $this->namaSupplier,
            'jumlah_bayar' => $this->jumlahBayar,
            'sisa_hutang' => $this->sisaHutang,
            'status_hutang' => $this->statusHutang,
            'tanggal_bayar' => $this->tanggalBayar,
            'waktu_event' => now()->toIso8601String(),
        ];
    }
}
