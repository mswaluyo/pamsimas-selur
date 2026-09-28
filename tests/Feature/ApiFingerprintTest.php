<?php

namespace Tests\Feature;

use App\Services\SslFingerprintService;
use Tests\TestCase;

/**
 * Kontrak endpoint /api/fingerprint untuk firmware ESP8266 (Network_SSL.ino):
 * respons plain text SHA1 panjang 21–59 karakter untuk client.setFingerprint();
 * JSON hanya bila diminta eksplisit; saat gagal → non-200 (firmware pakai mode insecure).
 */
class ApiFingerprintTest extends TestCase
{
    private const SHA1 = '53:0C:FD:23:8E:95:45:C2:54:25:C6:2A:1A:52:28:30:34:B5:CA:D6';

    /** Ganti service probe dengan stub agar tes tidak butuh koneksi TLS ke internet. */
    private function fakeService(array $result): void
    {
        $this->app->instance(SslFingerprintService::class, new class($result) extends SslFingerprintService {
            private array $result;

            public function __construct(array $result)
            {
                parent::__construct(1, 0, 0);
                $this->result = $result;
            }

            public function forHost(string $host): array
            {
                return $this->result;
            }
        });
    }

    private function okResult(): array
    {
        return [
            'ok' => true,
            'host' => 'pamsimas.selur.my.id',
            'sha1' => self::SHA1,
            'sha1_hex' => '530cfd238e9545c25425c62a1a52283034b5cad6',
            'sha256' => strtoupper(implode(':', str_split(bin2hex(random_bytes(32)), 2))),
            'subject' => 'selur.my.id',
            'issuer' => 'WE1',
            'valid_from' => '2026-01-01T00:00:00+00:00',
            'valid_to' => '2026-04-01T00:00:00+00:00',
            'target' => '104.21.82.214',
        ];
    }

    public function test_plain_text_sesuai_kontrak_firmware(): void
    {
        config(['services.fingerprint_host' => 'pamsimas.selur.my.id']);
        $this->fakeService($this->okResult());

        $response = $this->get('/api/fingerprint');
        $response->assertStatus(200);

        $body = (string) $response->getContent();
        $this->assertSame(59, strlen($body), 'harus 59 karakter agar muat di char fingerprint[60]');
        $this->assertMatchesRegularExpression('/^([0-9A-F]{2}:){19}[0-9A-F]{2}$/', $body);
        $this->assertGreaterThan(20, strlen($body), 'firmware menolak payload <= 20 karakter');
        $this->assertLessThan(60, strlen($body), 'firmware menolak payload >= 60 karakter');
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
    }

    public function test_json_hanya_bila_diminta(): void
    {
        config(['services.fingerprint_host' => 'pamsimas.selur.my.id']);
        $this->fakeService($this->okResult());

        $this->getJson('/api/fingerprint')
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.sha1', self::SHA1)
            ->assertJsonPath('data.issuer', 'WE1');

        // ?format=json juga memaksa JSON walau tanpa header Accept
        $this->get('/api/fingerprint?format=json')
            ->assertStatus(200)
            ->assertJsonPath('data.sha1_hex', '530cfd238e9545c25425c62a1a52283034b5cad6');
    }

    public function test_gagal_probe_memberi_503_plain_text(): void
    {
        config(['services.fingerprint_host' => 'pamsimas.selur.my.id']);
        $this->fakeService(['ok' => false, 'error' => 'connection refused']);

        $response = $this->get('/api/fingerprint');
        $response->assertStatus(503);
        $this->assertStringContainsString('Gagal mengambil fingerprint SSL', (string) $response->getContent());
    }

    public function test_kill_switch_mematikan_pinning(): void
    {
        config([
            'services.fingerprint_host' => 'pamsimas.selur.my.id',
            'services.fingerprint_disabled' => true,
        ]);
        $this->fakeService($this->okResult());

        $this->get('/api/fingerprint')->assertStatus(503);
    }

    public function test_host_tidak_dikonfigurasi_memberi_503(): void
    {
        config([
            'services.fingerprint_host' => null,
            'app.url' => 'http://localhost',
        ]);

        $this->get('/api/fingerprint')->assertStatus(503);
    }
}
