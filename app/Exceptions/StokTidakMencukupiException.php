<?php

namespace App\Exceptions;

use RuntimeException;

class StokTidakMencukupiException extends RuntimeException
{
    public function __construct(
        public readonly string $sku,
        public readonly string $namaBarang,
        public readonly int $stokTersedia,
        public readonly int $jumlahDiminta
    ) {
        parent::__construct(
            sprintf(
                'Stok untuk barang %s (%s) tidak mencukupi! Tersedia: %d %s, diminta: %d %s',
                $namaBarang,
                $sku,
                $stokTersedia,
                'pcs',
                $jumlahDiminta,
                'pcs'
            )
        );
    }
}
