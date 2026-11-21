<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Controller autentikasi: registrasi penyewa, login, dan logout (berbasis sesi).
 */
class AuthController extends Controller
{
    // Menampilkan halaman formulir registrasi dan login.
    public function showRegister() { return view('auth.register'); }
    public function showLogin() { return view('auth.login'); }

    /** Registrasi penyewa baru. Akun dibuat berstatus "pending" sampai diverifikasi admin. */
    public function register(Request $request)
    {
        $data = $request->validate([
            // Validasi input: NIK 16 digit & unik, email unik (dipakai sebagai username), password min. 6 + konfirmasi.
            'nik'      => ['required', 'digits:16', 'unique:users,nik'],
            'name'     => ['required', 'string', 'max:100'],
            'phone'    => ['required', 'string', 'max:20'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        // Password di-hash; peran selalu "penyewa" (admin hanya dibuat lewat seeder).
        User::create([...$data, 'password' => Hash::make($data['password']),
            'role' => 'penyewa', 'verification_status' => 'pending']);

        return redirect()->route('login')->with('status', 'Pendaftaran berhasil. Menunggu verifikasi admin.');
    }

    /** Login dengan email + password. Penyewa hanya boleh masuk bila sudah berstatus "accepted". */
    public function login(Request $request)
    {
        $cred = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);

        // Auth::attempt mencocokkan email dan hash password, lalu membuat sesi login.
        if (!Auth::attempt($cred)) {
            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        // Penyewa yang masih pending/ditolak langsung di-logout dan diberi pesan yang sesuai.
        $user = Auth::user();
        if ($user->role === 'penyewa' && $user->verification_status !== 'accepted') {
            Auth::logout();
            $msg = $user->verification_status === 'rejected'
                ? 'Pendaftaran Anda ditolak.' : 'Akun Anda belum diverifikasi admin.';
            return back()->withErrors(['email' => $msg]);
        }

        // Ganti ID sesi setelah login (mencegah session fixation), lalu arahkan sesuai peran.
        $request->session()->regenerate();
        return redirect()->route($user->role === 'admin' ? 'admin.reports' : 'rentals.index');
    }

    /** Logout: akhiri sesi dan buat token CSRF baru. */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
