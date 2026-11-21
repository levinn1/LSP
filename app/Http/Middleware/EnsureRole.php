<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware pembatas peran: hanya pengguna dengan peran tertentu yang boleh lewat.
 * Pemakaian di routes/web.php: ->middleware(EnsureRole::class . ':admin')
 */
class EnsureRole
{
    // $role = parameter setelah tanda ":" pada definisi rute (admin / penyewa).
    public function handle(Request $request, Closure $next, string $role)
    {
        // Jika peran tidak cocok, tolak dengan HTTP 403 (Forbidden).
        if ($request->user()?->role !== $role) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }
        return $next($request);
    }
}
