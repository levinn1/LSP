<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Pembatas peran: ->middleware(EnsureRole::class . ':admin') */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        if ($request->user()?->role !== $role) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }
        return $next($request);
    }
}
