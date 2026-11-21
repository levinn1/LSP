<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Mengisi data awal: satu akun admin (admin@garasi.test / password).
 * Jalankan dengan: php artisan db:seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // updateOrCreate: aman dijalankan berulang (tidak membuat admin ganda).
        User::updateOrCreate(['email' => 'admin@garasi.test'], [
            'name' => 'Admin',
            // Password demo; ganti bila dipakai di luar uji praktik.
            'password' => Hash::make('password'),
            'role' => 'admin',
            'verification_status' => 'accepted',
        ]);
    }
}
