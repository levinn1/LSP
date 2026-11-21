<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// Kolom yang boleh diisi massal. Semua kolom registrasi WAJIB ada di sini,
// kalau tidak, nilainya dibuang diam-diam dan database menolak insert (SQLSTATE 1364).
#[Fillable(['name', 'email', 'password', 'nik', 'phone', 'role', 'verification_status'])]
// Kolom yang disembunyikan saat model diubah menjadi array/JSON.
#[Hidden(['password', 'remember_token'])]
/**
 * Model User: akun pengguna dengan dua peran, "admin" dan "penyewa".
 *
 * Penyewa baru berstatus verification_status = pending sampai admin menerima (accepted)
 * atau menolak (rejected) pendaftarannya.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // Password otomatis di-hash saat diisi, jadi tidak pernah tersimpan sebagai teks biasa.
            'password' => 'hashed',
        ];
    }
}
