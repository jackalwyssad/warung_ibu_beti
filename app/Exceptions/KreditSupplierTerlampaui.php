<?php

namespace App\Exceptions;

use RuntimeException;

class KreditSupplierTerlampaui extends RuntimeException
{
    public function __construct(
        public readonly string $kodeSupplier,
        public readonly string $namaSupplier,
        public readonly int $totalHutangSaatIni,
        public readonly int $nilaiPesanan,
        public readonly int $limitKredit
    ) {
        parent::__construct(
            sprintf(
                'Limit kredit supplier %s (%s) terlampaui! Hutang saat ini: Rp%s, Nilai PO: Rp%s, Total: Rp%s, Limit: Rp%s',
                $namaSupplier,
                $kodeSupplier,
                number_format($totalHutangSaatIni, 0, ',', '.'),
                number_format($nilaiPesanan, 0, ',', '.'),
                number_format($totalHutangSaatIni + $nilaiPesanan, 0, ',', '.'),
                number_format($limitKredit, 0, ',', '.')
            )
        );
    }
}
