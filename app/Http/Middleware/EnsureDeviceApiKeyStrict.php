<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Proteksi endpoint maintenance yang merusak data (mis. /api/system/cleanup):
 * X-API-KEY WAJIB ada dan valid. Berbeda dari EnsureDeviceApiKey yang masih
 * meloloskan firmware lama tanpa key, middleware ini tidak punya fallback.
 */
class EnsureDeviceApiKeyStrict
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-KEY') ?? $request->header('X-Api-Key')
            ?? $request->query('api_key') ?? $request->input('api_key');
        $expected = (string) config('services.device_api_key');

        if ($apiKey === null || $apiKey === '' || !hash_equals($expected, (string) $apiKey)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid API Key'], 401);
        }

        return $next($request);
    }
}
