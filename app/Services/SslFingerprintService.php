<?php

namespace App\Services;

/**
 * Mengambil fingerprint (SHA1) sertifikat SSL yang DIHADAPI perangkat saat menghubungi
 * domain publik. Dipakai endpoint /api/fingerprint karena firmware ESP8266
 * (Network_SSL.ino::fetchServerFingerprint) memakai nilainya untuk client.setFingerprint()
 * — jadi yang dilaporkan harus sertifikat milik edge/CDN yang benar-benar dihubungi perangkat,
 * bukan sertifikat origin.
 *
 * Kenapa DoH + koneksi ke IP: di server produksi hostname publik dipetakan ke 127.0.0.1 pada
 * /etc/hosts (agar website tetap bisa diakses dari dalam server), sehingga koneksi TLS ke
 * hostname gagal / justru mengenai sertifikat origin. IP publik diselesaikan lewat
 * DNS-over-HTTPS, lalu TLS dijalankan ke IP tersebut dengan SNI hostname asli.
 */
class SslFingerprintService
{
    /** Batas waktu (detik) per koneksi TLS/HTTP. */
    protected int $timeout;

    /** Umur cache hasil sukses (detik). */
    protected int $ttlOk;

    /** Umur cache hasil gagal (detik) — cegah probe beruntun saat TLS bermasalah. */
    protected int $ttlFail;

    public function __construct(int $timeout = 8, int $ttlOk = 600, int $ttlFail = 30)
    {
        $this->timeout = $timeout;
        $this->ttlOk = $ttlOk;
        $this->ttlFail = $ttlFail;
    }

    /**
     * Hasil untuk satu host (di-cache):
     * ['ok' => true, 'sha1' => 'AB:CD:...', 'sha1_hex' => 'abcd...', 'sha256' => '...',
     *  'subject' => '...', 'issuer' => '...', 'valid_from' => ISO, 'valid_to' => ISO, 'target' => IP/host]
     * atau ['ok' => false, 'error' => '...'].
     */
    public function forHost(string $host): array
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return ['ok' => false, 'error' => 'host kosong'];
        }

        $cacheKey = 'ssl_fingerprint:' . $host;
        try {
            $cached = cache()->get($cacheKey);
            if (is_array($cached) && array_key_exists('ok', $cached)) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // cache bermasalah → lanjut probe langsung
        }

        $result = $this->probeHost($host);

        try {
            cache()->put($cacheKey, $result, ($result['ok'] ?? false) ? $this->ttlOk : $this->ttlFail);
        } catch (\Throwable $e) {
            // best-effort
        }

        return $result;
    }

    /**
     * Coba semua kandidat target: IP publik (hasil DoH) lebih dulu, lalu hostname apa adanya
     * (untuk server yang DNS-nya normal / tanpa override di /etc/hosts).
     */
    protected function probeHost(string $host): array
    {
        $targets = [];
        foreach ($this->resolvePublicIps($host) as $ip) {
            $targets[] = [$ip, $host];
        }
        $targets[] = [$host, $host];

        $errors = [];
        foreach ($targets as [$target, $sni]) {
            $probe = $this->probe($target, $sni, $host);
            if ($probe['ok'] ?? false) {
                $probe['target'] = $target;
                return $probe;
            }
            $errors[] = $target . ': ' . ($probe['error'] ?? 'gagal');
        }

        return ['ok' => false, 'error' => $errors ? implode('; ', $errors) : 'tidak ada target'];
    }

    /**
     * Buka koneksi TLS ke target (IP/host) dengan SNI hostname lalu baca sertifikat peer.
     * Hanya MEMBACA sertifikat (rantai/CA tidak divalidasi di sini karena perangkat yang
     * mem-pin SHA1-nya), tetapi nama host wajib cocok agar tidak salah lapor.
     */
    protected function probe(string $target, string $sniHost, string $expectedHost): array
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $sniHost,
            'timeout' => $this->timeout,
        ]]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            "ssl://{$target}:443",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            return ['ok' => false, 'error' => $errstr !== '' ? $errstr : 'gagal koneksi TLS'];
        }

        $params = stream_context_get_params($socket);
        @fclose($socket);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            return ['ok' => false, 'error' => 'sertifikat peer tidak terambil'];
        }

        $info = openssl_x509_parse($cert);
        if (!is_array($info)) {
            return ['ok' => false, 'error' => 'gagal membaca sertifikat'];
        }

        if (!$this->hostMatches($info, $expectedHost)) {
            $cn = $info['subject']['CN'] ?? '-';
            return ['ok' => false, 'error' => "sertifikat tidak cocok untuk {$expectedHost} (CN={$cn})"];
        }

        $sha1 = openssl_x509_fingerprint($cert, 'sha1');
        if (!is_string($sha1) || strlen($sha1) !== 40) {
            return ['ok' => false, 'error' => 'fingerprint SHA1 tidak valid'];
        }

        return [
            'ok' => true,
            'host' => $expectedHost,
            'sha1' => $this->format($sha1),
            'sha1_hex' => strtolower($sha1),
            'sha256' => $this->format((string) openssl_x509_fingerprint($cert, 'sha256')),
            'subject' => (string) ($info['subject']['CN'] ?? ''),
            'issuer' => (string) ($info['issuer']['CN'] ?? ''),
            'valid_from' => isset($info['validFrom_time_t']) ? date('c', (int) $info['validFrom_time_t']) : '',
            'valid_to' => isset($info['validTo_time_t']) ? date('c', (int) $info['validTo_time_t']) : '',
        ];
    }

    /**
     * Bentuk fingerprint yang diharapkan client.setFingerprint(): HURUF BESAR, dipisah ':'
     * tiap 2 digit (SHA1 = 59 karakter, muat di char fingerprint[60] firmware).
     */
    public function format(string $fingerprint): string
    {
        $hex = (string) preg_replace('/[^0-9a-fA-F]/', '', $fingerprint);
        if ($hex === '') {
            return '';
        }

        return rtrim(strtoupper(chunk_split($hex, 2, ':')), ':');
    }

    /**
     * Resolusi IP publik via DNS-over-HTTPS (tidak terpengaruh /etc/hosts lokal).
     */
    protected function resolvePublicIps(string $host): array
    {
        $endpoints = [
            'https://cloudflare-dns.com/dns-query?name=%s&type=A',
            'https://dns.google/resolve?name=%s&type=A',
            'https://dns.quad9.net:5053/dns-query?name=%s&type=A',
        ];

        foreach ($endpoints as $template) {
            $body = $this->httpGet(sprintf($template, urlencode($host)));
            if ($body === null || $body === '') {
                continue;
            }
            $json = json_decode($body, true);
            if (!is_array($json)) {
                continue;
            }
            $ips = [];
            foreach ((array) ($json['Answer'] ?? []) as $answer) {
                $data = (string) ($answer['data'] ?? '');
                if (($answer['type'] ?? 0) === 1 && filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ips[] = $data;
                }
            }
            if ($ips) {
                return array_values(array_unique($ips));
            }
        }

        return [];
    }

    protected function httpGet(string $url): ?string
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "accept: application/dns-json\r\n",
            'timeout' => $this->timeout,
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents($url, false, $context);

        return $body === false ? null : $body;
    }

    /**
     * Cocokkan host dengan CN/SAN sertifikat, termasuk wildcard ('*.selur.my.id').
     */
    protected function hostMatches(array $info, string $host): bool
    {
        $host = strtolower($host);
        $names = [];

        $san = (string) ($info['extensions']['subjectAltName'] ?? '');
        foreach ($san !== '' ? explode(',', $san) : [] as $part) {
            $part = trim($part);
            if (stripos($part, 'DNS:') === 0) {
                $names[] = strtolower(trim(substr($part, 4)));
            }
        }
        if (!empty($info['subject']['CN'])) {
            $names[] = strtolower((string) $info['subject']['CN']);
        }

        foreach ($names as $name) {
            if ($name === $host) {
                return true;
            }
            if (str_starts_with($name, '*.')) {
                $suffix = substr($name, 1); // ".selur.my.id"
                $label = substr($host, 0, -strlen($suffix));
                if (str_ends_with($host, $suffix) && $label !== '' && !str_contains($label, '.')) {
                    return true;
                }
            }
        }

        return false;
    }
}
