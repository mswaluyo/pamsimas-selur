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
        $expected = (string) config('services.device_api_key');
        $keyValid = false;

        if ($apiKey !== null && $apiKey !== '') {
            // Key DIBERIKAN → wajib valid.
            if (!hash_equals($expected, (string) $apiKey)) {
                return response()->json(['status' => 'error', 'message' => 'Invalid API Key'], 401);
            }
            $keyValid = true;
        }
        // Firmware lama TIDAK mengirim X-API-KEY — tetap diloloskan.
        // Otorisasi aksi sensitif (set_mode/set_manual_status) diverifikasi
        // di controller: butuh session login ATAU key valid.

        $request->attributes->set('device_key_valid', $keyValid);
        $this->noteConnection($request);
        $this->recordDetected($request);

        return $next($request);
    }

    /**
     * Catat transisi koneksi perangkat ke event_logs (Task #42 — log koneksi):
     * - "Koneksi terputus" (jangkar = last_update / kontak terakhir) bila belum tercatat,
     * - "Koneksi tersambung — perangkat online (offline {durasi})" saat perangkat
     *   yang sedang offline menghubungi server kembali.
     *
     * Dijalankan di handle() SEBELUM controller memperbarui last_update, dan hanya
     * untuk request firmware (tanpa sesi login) — perintah dashboard memakai
     * /api/device-command yang tidak melewati middleware ini. Semua endpoint
     * device.api (/api/log|status|update|device/update|health|log-offline) tercakup.
     */
    private function noteConnection(Request $request): void
    {
        if ($request->user()) {
            return; // sesi dashboard web — bukan kontak perangkat.
        }
        $raw = $request->input('mac_address') ?? $request->input('mac') ?? $request->query('mac_address') ?? $request->query('mac');
        $mac = strtoupper(trim((string) $raw));
        if ($mac === '' || !preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            return;
        }

        try {
            $device = \App\Models\Device::where('mac_address', $mac)->first();
            if (!$device || ! $device->last_update || $device->isOnline()) {
                return; // belum pernah kontak, atau masih online → bukan transisi koneksi.
            }

            $since = $device->last_update->copy();
            // Pasangan "terputus" bila belum pernah tercatat (mis. tak ada yang
            // membuka halaman detail selama masa offline).
            \App\Models\EventLog::logDisconnect((int) $device->id, $since);

            $secs = max(0, now()->getTimestamp() - $since->getTimestamp());
            $mins = intdiv($secs, 60);
            $dur = $mins < 60
                ? "{$mins} menit"
                : intdiv($mins, 60) . ' jam ' . ($mins % 60) . ' menit';

            \App\Models\EventLog::create([
                'device_id' => $device->id,
                'event_type' => 'Koneksi',
                'message' => "Koneksi tersambung — perangkat online (offline {$dur})",
                'event_time' => now(),
            ]);
        } catch (\Throwable $e) {
            // Log koneksi best-effort — kegagalan tidak boleh menggagalkan request perangkat.
        }
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
