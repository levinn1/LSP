<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RentalController;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(EnsureRole::class . ':penyewa')->group(function () {
        Route::get('/rentals', [RentalController::class, 'index'])->name('rentals.index');
        Route::get('/rentals/create', [RentalController::class, 'create'])->name('rentals.create');
        Route::get('/rentals/available-garages', [RentalController::class, 'availableGarages'])->name('rentals.available');
        Route::post('/rentals', [RentalController::class, 'store'])->name('rentals.store');
    });

    Route::prefix('admin')->name('admin.')->middleware(EnsureRole::class . ':admin')->group(function () {
        Route::get('/garages', [Admin\GarageController::class, 'index'])->name('garages.index');
        Route::get('/garages/create', [Admin\GarageController::class, 'create'])->name('garages.create');
        Route::post('/garages', [Admin\GarageController::class, 'store'])->name('garages.store');

        Route::get('/verifications', [Admin\VerificationController::class, 'index'])->name('verifications.index');
        Route::post('/verifications/{user}/accept', [Admin\VerificationController::class, 'accept'])->name('verifications.accept');
        Route::post('/verifications/{user}/reject', [Admin\VerificationController::class, 'reject'])->name('verifications.reject');

        Route::get('/extensions', [Admin\ExtensionController::class, 'index'])->name('extensions.index');
        Route::post('/extensions/{rental}', [Admin\ExtensionController::class, 'store'])->name('extensions.store');

        Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports');
    });
});
