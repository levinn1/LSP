<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // tambah role pengguna dan buat tabel penyewaan garasi
    public function up(): void
    {
        // tambah identitas penyewa, nomor telepon, peran, dan status verif ke tabel pengguna
        Schema::table('users', function (Blueprint $t) {
            $t->string('nik', 16)->unique()->nullable();
            $t->string('phone', 20)->nullable();
            $t->enum('role', ['admin', 'penyewa'])->default('penyewa');
            $t->enum('verification_status', ['pending', 'accepted', 'rejected'])->default('pending');
        });

        // simpan data garasi
        Schema::create('garages', function (Blueprint $t) {
            $t->id();
            $t->string('code', 4)->unique();          
            $t->enum('type', ['sepeda', 'mobil', 'mobil_listrik']);
            $t->unsignedInteger('daily_price');
            $t->unsignedInteger('price_per_kwh')->nullable(); // khusus mobil listrik
            $t->timestamps();
        });

        // simpan data kendaraan yang didaftar penyewa
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['sepeda', 'motor', 'mobil', 'mobil_listrik']);
            $t->string('plate', 12)->nullable();             // null untuk sepeda
            $t->string('brand');
            $t->string('model');
            $t->decimal('battery_capacity_kwh', 6, 2)->nullable();
            $t->timestamps();
        });

        // simpan tanggal, kendaraan, garasi, biaya, dan bukti pembayaran transaksi sewa
        Schema::create('rentals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('garage_id')->constrained();
            $t->foreignId('vehicle_id')->constrained();
            $t->enum('rental_type', ['harian', 'bulanan']);
            $t->unsignedTinyInteger('days');                 
            $t->date('start_date');
            $t->date('end_date');                            // berubah kalau ada perpanjangan
            $t->decimal('battery_remaining_kwh', 6, 2)->nullable();
            $t->decimal('kwh_to_charge', 6, 2)->nullable();
            $t->unsignedBigInteger('total_cost');
            $t->string('payment_proof');
            $t->timestamps();
            $t->index(['garage_id', 'start_date', 'end_date']);
        });

        // buat tabel untuk simpan riwayat perpanjang sewa
        Schema::create('rental_extensions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $t->foreignId('admin_id')->constrained('users');
            $t->date('old_end_date');
            $t->date('new_end_date');
            $t->unsignedBigInteger('additional_cost');
            $t->string('payment_proof');
            $t->timestamps();
        });
    }

    // hapus tabel kalau migrasi dibatalkan
    public function down(): void
    {
        Schema::dropIfExists('rental_extensions');
        Schema::dropIfExists('rentals');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('garages');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['nik', 'phone', 'role', 'verification_status']));
    }
};
