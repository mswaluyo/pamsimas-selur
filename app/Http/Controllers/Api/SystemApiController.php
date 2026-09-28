<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetectedDevice;
use App\Models\EventLog;
use App\Models\PumpLog;
use App\Models\SensorLog;
use App\Services\SslFingerprintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemApiController extends Controller
{
    /**
     * GET /api/system/detected-devices — MAC yang mencoba akses tapi belum terdaftar.
     */
    public function detectedDevices()
    {
        return response()->json([
            'status' => 'success',
            'data' => DetectedDevice::orderByDesc('last_seen')->get(),
        ]);
    }

    /**
     * GET /api/fingerprint — fingerprint SSL server yang dihadapi perangkat.
     *
     * Kontrak FIRMWARE (Network_SSL.ino::fetchServerFingerprint): respons harus berupa
     * teks biasa panjang 21–59 karakter (disimpan ke `char fingerprint[60]`, dipakai
     * client.setFingerprint()). Karena itu respons utama = SHA1 sertifikat dalam bentuk
     * "AB:CD:…" (59 karakter) — sama seperti sistem lama. Jika endpoint ini membalas JSON
     * (seperti sebelumnya), panjangnya > 59 → firmware menolak dan turun ke mode insecure.
     *
     * JSON hanya diberikan bila diminta eksplisit (?format=json atau Accept: application/json)
     * untuk keperluan inspeksi admin, dan non-200 (mis. 503) berarti firmware tetap memakai
     * mode insecure — perilaku aman, perangkat tidak ikut rusak.
     */
    public function fingerprint(Request $request)
    {
        $host = (string) (config('services.fingerprint_host') ?: parse_url((string) config('app.url'), PHP_URL_HOST));
        $wantJson = $request->query('format') === 'json'
            || str_contains(strtolower((string) $request->header('Accept', '')), 'application/json');

        $fail = function (string $message, int $status = 503) use ($wantJson) {
            return $wantJson
                ? response()->json(['status' => 'error', 'message' => $message], $status)
                : response($message, $status)->header('Content-Type', 'text/plain; charset=utf-8');
        };

        // Kill-switch darurat: paksa firmware memakai mode insecure (tanpa pinning).
        if (config('services.fingerprint_disabled')) {
            return $fail('Fingerprint dinonaktifkan sementara (FINGERPRINT_DISABLED=true).');
        }

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return $fail('Host fingerprint belum dikonfigurasi (set FINGERPRINT_HOST atau APP_URL).');
        }

        $result = app(SslFingerprintService::class)->forHost($host);

        if (!($result['ok'] ?? false)) {
            return $fail('Gagal mengambil fingerprint SSL: ' . ($result['error'] ?? 'tidak diketahui'));
        }

        if ($wantJson) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'host' => $result['host'],
                    'sha1' => $result['sha1'],
                    'sha1_hex' => $result['sha1_hex'],
                    'sha256' => $result['sha256'],
                    'subject' => $result['subject'],
                    'issuer' => $result['issuer'],
                    'valid_from' => $result['valid_from'] ?? null,
                    'valid_to' => $result['valid_to'] ?? null,
                    'verified_target' => $result['target'] ?? null,
                    'server_time' => now()->toDateTimeString(),
                ],
            ]);
        }

        return response($result['sha1'], 200)->header('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * GET /api/system/cleanup — pembersihan log lama (dipanggil cron).
     */
    public function cleanupLogs()
    {
        $deleted = [
            'sensor_logs' => SensorLog::where('record_time', '<', now()->subDays(90))->count(),
            'pump_logs' => PumpLog::where('timestamp', '<', now()->subDays(90))->count(),
            'event_logs' => EventLog::where('event_time', '<', now()->subDays(30))->count(),
        ];

        SensorLog::where('record_time', '<', now()->subDays(90))->delete();
        PumpLog::where('timestamp', '<', now()->subDays(90))->delete();
        EventLog::where('event_time', '<', now()->subDays(30))->delete();

        // Optimasi tabel
        DB::statement('OPTIMIZE TABLE sensor_logs, pump_logs, event_logs');

        return response()->json(['status' => 'success', 'deleted' => $deleted]);
    }
}
