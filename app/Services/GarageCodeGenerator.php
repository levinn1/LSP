<?php

namespace App\Services;

use InvalidArgumentException;

class GarageCodeGenerator
{
    public const MAX_PER_BATCH = 999;
    private const PREFIX = ['sepeda' => 'S', 'mobil' => 'C', 'mobil_listrik' => 'E'];

    /**
     * @param int $lastNumber nomor terakhir yang sudah ada untuk tipe tsb (0 jika belum ada)
     * @return string[] mis. ['S001','S002']
     */
    public function generate(string $type, int $count, int $lastNumber = 0): array
    {
        if (!isset(self::PREFIX[$type])) {
            throw new InvalidArgumentException('Tipe garasi tidak dikenal.');
        }
        if ($count < 1 || $count > self::MAX_PER_BATCH) {
            throw new InvalidArgumentException('Jumlah garasi harus 1 sampai 999 per batch.');
        }
        if ($lastNumber + $count > 999) {
            throw new InvalidArgumentException('Kode garasi melebihi 999 untuk tipe ini.');
        }

        $codes = [];
        for ($i = 1; $i <= $count; $i++) {
            $codes[] = self::PREFIX[$type] . str_pad((string) ($lastNumber + $i), 3, '0', STR_PAD_LEFT);
        }
        return $codes;
    }
}
