# Langkah integrasi lapisan HTTP
1. Salin `app/` dan `routes/web.php` ke proyek (gabungkan dengan paket garasi-core).
2. Model `User`: tambahkan `nik`, `phone`, `role`, `verification_status` ke daftar fillable.
3. `php artisan storage:link` (bukti bayar disimpan di disk `public`).
4. (Opsional) `composer remove tymon/jwt-auth` karena memakai auth sesi.
5. Buat seeder satu admin: role=admin, verification_status=accepted.
6. View Blade yang dibutuhkan: auth.login, auth.register, rentals.index, rentals.create,
   admin.garages.index/create, admin.verifications.index, admin.extensions.index, admin.reports.index.
