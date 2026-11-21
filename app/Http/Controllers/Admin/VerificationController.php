<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

/**
 * Controller admin: verifikasi pendaftar baru (terima / tolak setelah NIK diperiksa manual).
 */
class VerificationController extends Controller
{
    /** Daftar penyewa yang masih berstatus "pending". */
    public function index()
    {
        return view('admin.verifications.index', [
            'users' => User::where('role', 'penyewa')->where('verification_status', 'pending')->latest()->get(),
        ]);
    }

    // Terima atau tolak pendaftaran; keduanya memakai setStatus().
    public function accept(User $user) { return $this->setStatus($user, 'accepted'); }
    public function reject(User $user) { return $this->setStatus($user, 'rejected'); }

    /** Mengubah status verifikasi penyewa lalu kembali ke halaman sebelumnya dengan pesan. */
    private function setStatus(User $user, string $status)
    {
        $user->update(['verification_status' => $status]);
        return back()->with('status', "Pendaftar {$user->name}: {$status}.");
    }
}
