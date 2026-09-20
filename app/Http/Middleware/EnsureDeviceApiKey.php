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
        $apiKey = $request->header('X-API-KEY') ?? $request->header('X-Api-Key')
            ?? $request->query('api_key') ?? $request->input('api_key');

        // Endpoint publik: status & health boleh tanpa API key (ESP lama tidak kirim key).
        // Deteksi MAC tetap dicatat agar muncul di "Perangkat Terdeteksi".
        if ($request->is('api/status') || $request->is('api/health')) {
            $this->recordDetected($request);
            return $next($request);
        }

        $expected = config('services.device_api_key');

        if (!$apiKey || !hash_equals((string) $expected, (string) $apiKey)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid API Key'], 401);
        }

        $this->recordDetected($request);

        return $next($request);
    }

    /**
     * Catat MAC perangkat tak dikenal ke detected_devices (idempoten).
     */
    private function recordDetected(Request $request): void
    {
        $raw = $request->input('mac_address') ?? $request->input('mac') ?? $request->query('mac_address') ?? $request->query('mac');
        $mac = strtoupper(trim((string) $raw));
        if ($mac === '' || !preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            return;
        }
        // Sudah terdaftar resmi → tidak perlu dicatat sebagai "terdeteksi".
        if (\App\Models\Device::where('mac_address', $mac)->exists()) {
            return;
        }
        try {
            $detected = DetectedDevice::firstOrNew(['mac_address' => $mac]);
            $detected->last_seen = now();
            $detected->hits = ($detected->hits ?? 0) + 1;
            $detected->fingerprint = substr(sha1($mac . $request->userAgent()), 0, 255);
            $detected->first_seen = $detected->first_seen ?? now();
            $detected->save();
        } catch (\Throwable $e) {
            // Deteksi bersifat best-effort — jangan gagalkan request perangkat.
        }
    }
}
