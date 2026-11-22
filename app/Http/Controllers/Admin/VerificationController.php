<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class VerificationController extends Controller
{
    // tampilkan pendaftar yang status masih pending
    public function index()
    {
        return view('admin.verifications.index', [
            'users' => User::where('role', 'penyewa')->where('verification_status', 'pending')->latest()->get(),
        ]);
    }

    // accept akun penyewa yang daftar
    public function accept(User $user) { return $this->setStatus($user, 'accepted'); }
    // reject akun penyewa yang daftar
    public function reject(User $user) { return $this->setStatus($user, 'rejected'); }

    // simpan status verif
    private function setStatus(User $user, string $status)
    {
        $user->update(['verification_status' => $status]);
        return back()->with('status', "Pendaftar {$user->name}: {$status}.");
    }
}
