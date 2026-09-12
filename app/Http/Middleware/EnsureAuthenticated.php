<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Proteksi halaman web: wajib login (session based, seperti sistem asli).
 */
class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('user')) {
            return redirect()->route('login');
        }
        return $next($request);
    }
}
