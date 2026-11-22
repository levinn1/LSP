<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    // buat atau update akun admin
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@garasi.test'], [
            'name' => 'Admin',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'verification_status' => 'accepted',
        ]);
    }
}
