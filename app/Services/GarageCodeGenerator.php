<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Membuat kode ID garasi secara bertahap (incremental).
 *
 * Format: huruf awal tipe + 3 digit, mis. S001 (sepeda), C010 (mobil), E001 (mobil listrik).
 * Kelas ini tidak menyentuh database sehingga mudah diuji unit.
 */
class GarageCodeGenerator
{
    // Batas jumlah garasi yang boleh dibuat dalam satu kali input (sesuai soal).
    public const MAX_PER_BATCH = 999;
    // Huruf awal kode untuk setiap tipe garasi.
    private const PREFIX = ['sepeda' => 'S', 'mobil' => 'C', 'mobil_listrik' => 'E'];

    /**
     * Menghasilkan daftar kode baru.
     *
     * @param string $type       sepeda | mobil | mobil_listrik
     * @param int    $count      jumlah kode yang dibuat (1..999)
     * @param int $lastNumber nomor terakhir yang sudah ada untuk tipe tsb (0 jika belum ada)
     * @return string[] mis. ['S001','S002']
     */
    public function generate(string $type, int $count, int $lastNumber = 0): array
    {
        // Validasi 1: tipe harus dikenal.
        if (!isset(self::PREFIX[$type])) {
            throw new InvalidArgumentException('Tipe garasi tidak dikenal.');
        }
        // Validasi 2: jumlah per batch harus 1..999.
        if ($count < 1 || $count > self::MAX_PER_BATCH) {
            throw new InvalidArgumentException('Jumlah garasi harus 1 sampai 999 per batch.');
        }
        // Validasi 3: nomor akhir tidak boleh lewat 999 karena hanya 3 digit.
        if ($lastNumber + $count > 999) {
            throw new InvalidArgumentException('Kode garasi melebihi 999 untuk tipe ini.');
        }

        // Bentuk kode: prefix + nomor yang diisi nol di kiri (1 => "001"), dimulai dari nomor terakhir + 1.
        $codes = [];
        for ($i = 1; $i <= $count; $i++) {
            $codes[] = self::PREFIX[$type] . str_pad((string) ($lastNumber + $i), 3, '0', STR_PAD_LEFT);
        }
        return $codes;
    }
}
