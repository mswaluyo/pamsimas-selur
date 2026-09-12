<?php

namespace App\Http\Middleware;

use App\Models\DetectedDevice;
use Closure;
use Illuminate\Http\Request;

/**
 * Autentikasi perangkat IoT via header X-API-KEY.
 * MAC address yang belum terdaftar dicatat ke tabel detected_devices.
 */
class EnsureDeviceApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-KEY') ?? $request->query('api_key');
        $expected = config('services.device_api_key');

        if (!$apiKey || !hash_equals((string) $expected, (string) $apiKey)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid API Key'], 401);
        }

        // Deteksi MAC untuk perangkat tak dikenal
        if ($mac = $request->input('mac_address') ?? $request->input('mac')) {
            $detected = DetectedDevice::firstOrNew(['mac_address' => strtoupper($mac)]);
            $detected->last_seen = now();
            $detected->hits = ($detected->hits ?? 0) + 1;
            $detected->fingerprint = substr(sha1($mac . $request->userAgent()), 0, 255);
            $detected->first_seen = $detected->first_seen ?? now();
            $detected->save();
        }

        return $next($request);
    }
}
