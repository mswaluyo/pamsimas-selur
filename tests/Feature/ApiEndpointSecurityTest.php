<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Audit Fase 1 (Critical): endpoint data UI wajib login, endpoint perangkat tetap jalan.
 * Tanpa RefreshDatabase — hanya menguji middleware & routing (session driver 'array').
 */
class ApiEndpointSecurityTest extends TestCase
{
    /** Endpoint data UI: tanpa sesi login → 401 JSON (bukan redirect HTML). */
    public function test_data_endpoints_require_login(): void
    {
        $urls = [
            '/api/dashboard/data',
            '/api/dashboard-data',
            '/api/device/history?device_id=1&range=1h',
            '/api/system/detected-devices',
            '/api/detected-devices',
            '/api/terminal/events',
            '/api/meter/last/1',
        ];

        foreach ($urls as $url) {
            $response = $this->getJson($url);
            $response->assertStatus(401);
            $response->assertJsonPath('status', 'error');
        }
    }

    /** Aksi destruktif via POST dari luar sesi tidak boleh dieksekusi. */
    public function test_destructive_endpoints_blocked_without_session(): void
    {
        $this->postJson('/api/terminal/clear')->assertStatus(401);
        $this->postJson('/api/device-command', [
            'mac' => '08:B6:1F:B1:3F:80',
            'action' => 'set_mode',
            'value' => 'MANUAL',
        ])->assertStatus(401);
    }

    /** Maintenance cleanup wajib X-API-KEY valid (tanpa fallback firmware lama). */
    public function test_system_cleanup_requires_api_key(): void
    {
        $this->getJson('/api/system/cleanup')->assertStatus(401);

        $this->getJson('/api/system/cleanup', ['X-API-KEY' => 'kunci-salah'])->assertStatus(401);

        $key = (string) config('services.device_api_key');
        $this->assertNotEmpty($key, 'DEVICE_API_KEY belum dikonfigurasi.');
    }

    /** Fingerprint (handshake firmware) & endpoint perangkat tetap dapat diakses. */
    public function test_firmware_endpoints_stay_reachable(): void
    {
        $this->getJson('/api/fingerprint')->assertStatus(200);
        $this->getJson('/api/status')->assertStatus(422); // lolos middleware device.api, gagal validasi MAC
    }

    /** Halaman login dibatasi throttle (anti brute-force) — payload invalid agar tanpa akses DB. */
    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $payload = ['username' => str_repeat('a', 51), 'password' => 'x']; // max:50 → gagal validasi

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', $payload)->assertStatus(302);
        }

        $this->post('/login', $payload)->assertStatus(429);
    }
}
