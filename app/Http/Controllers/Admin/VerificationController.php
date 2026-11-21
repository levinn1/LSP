<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class VerificationController extends Controller
{
    public function index()
    {
        return view('admin.verifications.index', [
            'users' => User::where('role', 'penyewa')->where('verification_status', 'pending')->latest()->get(),
        ]);
    }

    public function accept(User $user) { return $this->setStatus($user, 'accepted'); }
    public function reject(User $user) { return $this->setStatus($user, 'rejected'); }

    private function setStatus(User $user, string $status)
    {
        $user->update(['verification_status' => $status]);
        return back()->with('status', "Pendaftar {$user->name}: {$status}.");
    }
}
