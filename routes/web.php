<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RentalController;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;

// Halaman awal langsung diarahkan ke halaman login.
Route::redirect('/', '/login');

// Rute untuk tamu (belum login): registrasi dan login.
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Rute yang wajib login. Di dalamnya dibagi lagi menurut peran.
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Khusus penyewa: riwayat, form sewa, endpoint garasi tersedia (JSON), dan simpan sewa.
    Route::middleware(EnsureRole::class . ':penyewa')->group(function () {
        Route::get('/rentals', [RentalController::class, 'index'])->name('rentals.index');
        Route::get('/rentals/create', [RentalController::class, 'create'])->name('rentals.create');
        Route::get('/rentals/available-garages', [RentalController::class, 'availableGarages'])->name('rentals.available');
        Route::post('/rentals', [RentalController::class, 'store'])->name('rentals.store');
    });

    // Khusus admin (URL berawalan /admin, nama rute berawalan "admin."): garasi, verifikasi, perpanjangan, laporan.
    Route::prefix('admin')->name('admin.')->middleware(EnsureRole::class . ':admin')->group(function () {
        Route::get('/garages', [Admin\GarageController::class, 'index'])->name('garages.index');
        Route::get('/garages/create', [Admin\GarageController::class, 'create'])->name('garages.create');
        Route::post('/garages', [Admin\GarageController::class, 'store'])->name('garages.store');
        // {garage} memakai Route Model Binding. Edit memakai PUT, hapus memakai DELETE (lewat @method di form).
        Route::get('/garages/{garage}/edit', [Admin\GarageController::class, 'edit'])->name('garages.edit');
        Route::put('/garages/{garage}', [Admin\GarageController::class, 'update'])->name('garages.update');
        Route::delete('/garages/{garage}', [Admin\GarageController::class, 'destroy'])->name('garages.destroy');

        Route::get('/verifications', [Admin\VerificationController::class, 'index'])->name('verifications.index');
        Route::post('/verifications/{user}/accept', [Admin\VerificationController::class, 'accept'])->name('verifications.accept');
        Route::post('/verifications/{user}/reject', [Admin\VerificationController::class, 'reject'])->name('verifications.reject');

        Route::get('/extensions', [Admin\ExtensionController::class, 'index'])->name('extensions.index');
        Route::post('/extensions/{rental}', [Admin\ExtensionController::class, 'store'])->name('extensions.store');

        Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports');
    });
});
