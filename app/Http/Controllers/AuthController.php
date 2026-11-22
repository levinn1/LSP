<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // tampilkan page pendaftaran akun
    public function showRegister() { return view('auth.register'); }
    // tampilkan page login
    public function showLogin() { return view('auth.login'); }

    // validasi data pendaftar
    public function register(Request $request)
    {
        $data = $request->validate([
            'nik'      => ['required', 'digits:16', 'unique:users,nik'],
            'name'     => ['required', 'string', 'max:100'],
            'phone'    => ['required', 'string', 'max:20'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        // tentukan role penyewa dan tetapkan status verif
        User::create([...$data, 'password' => Hash::make($data['password']),
            'role' => 'penyewa', 'verification_status' => 'pending']);

        return redirect()->route('login')->with('status', 'Pendaftaran berhasil. Menunggu verifikasi admin.');
    }

    // cek info login dan apakah akun sudah verif
    public function login(Request $request)
    {
        $cred = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);

        if (!Auth::attempt($cred)) {
            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        $user = Auth::user();
        if ($user->role === 'penyewa' && $user->verification_status !== 'accepted') {
            Auth::logout();
            $msg = $user->verification_status === 'rejected'
                ? 'Pendaftaran Anda ditolak.' : 'Akun Anda belum diverifikasi admin.';
            return back()->withErrors(['email' => $msg]);
        }

        // perbarui session ID setelah autentikasi
        $request->session()->regenerate();
        return redirect()->route($user->role === 'admin' ? 'admin.reports' : 'rentals.index');
    }

    // akhiri autentikasi, hapus sesi lama, dan perbarui token CSRF
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
