<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Proteksi halaman web & endpoint data UI: wajib login (session based, seperti sistem asli).
 *
 * Untuk request ke jalur API (mis. /api/dashboard-data yang dipanggil fetch() dari
 * dashboard) respons berupa 401 JSON — bukan redirect HTML — supaya konsumen AJAX
 * tidak salah mengira mendapat JSON yang valid.
 */
class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('user')) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized — silakan login terlebih dahulu.',
                ], 401);
            }
            return redirect()->route('login');
        }
        return $next($request);
    }
}
