<?php

namespace App\Events;

class BarangDiterimaSupplier
{
    public function __construct(
        public readonly int $penerimaanId,
        public readonly string $nomorPenerimaan,
        public readonly string $nomorPo,
        public readonly string $kodeSupplier,
        public readonly string $namaSupplier,
        public readonly array $rincianBarang,
        public readonly string $statusPenerimaan,
        public readonly string $penerima,
        public readonly string $tanggalTerima
    ) {}

    public function toArray(): array
    {
        return [
            'penerimaan_id' => $this->penerimaanId,
            'nomor_penerimaan' => $this->nomorPenerimaan,
            'nomor_po' => $this->nomorPo,
            'kode_supplier' => $this->kodeSupplier,
            'nama_supplier' => $this->namaSupplier,
            'rincian_barang' => $this->rincianBarang,
            'status_penerimaan' => $this->statusPenerimaan,
            'penerima' => $this->penerima,
            'tanggal_terima' => $this->tanggalTerima,
            'waktu_event' => now()->toIso8601String(),
        ];
    }
}
