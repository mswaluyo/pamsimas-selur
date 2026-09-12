<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Kirim pesan WhatsApp via Gateway Node.js (Baileys).
 * Port dari app\Services\WhatsAppService sistem asli.
 */
class WhatsAppService
{
    /**
     * @param string|int $rawJid Nomor tujuan (angka saja atau JID lengkap).
     */
    public static function send(string|int $rawJid, string $message): ?array
    {
        // Pesan keluar wajib ke @s.whatsapp.net (LID hanya untuk pesan masuk)
        if (ctype_digit((string) $rawJid)) {
            $target = $rawJid . '@s.whatsapp.net';
        } elseif (!str_contains((string) $rawJid, '@')) {
            $target = $rawJid . '@s.whatsapp.net';
        } else {
            $target = (string) $rawJid;
        }

        $gatewayUrl = config('services.wa_gateway_url', 'http://127.0.0.1:3000/send-wa');

        try {
            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->post($gatewayUrl, ['phone' => $target, 'message' => $message]);

            if ($response->failed()) {
                Log::error("WA Gateway Error: HTTP {$response->status()}", ['body' => $response->body()]);
                return null;
            }
            return $response->json();
        } catch (\Throwable $e) {
            Log::error('WA Gateway Error: ' . $e->getMessage());
            return null;
        }
    }
}
