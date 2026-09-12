<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerValidation;
use App\Models\EventLog;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Webhook WhatsApp: menerima pesan masuk & foto dari WA Gateway (Node.js/Baileys).
 * - Pesan ''AKTIVASI-PTA-XXXX'' menautkan LID WhatsApp warga ke pelanggan.
 * - Foto meteran disimpan & dibuatkan antrean validasi (customer_validations).
 */
class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Proteksi sederhana: hanya menerima dari gateway (X-Pamsimas-Key)
        $expected = config('services.wa_gateway_secret', env('DEVICE_API_KEY'));
        if (!hash_equals((string) $expected, (string) $request->header('X-Pamsimas-Key'))) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $phone = $request->input('phone');       // JID utuh (bisa @lid)
        $type = $request->input('type', 'text');
        $text = trim((string) $request->input('text', ''));

        EventLog::create([
            'device_id' => 0,
            'event_type' => 'WA Inbox',
            'message' => "Pesan {$type} dari {$phone}" . ($text ? ": {$text}" : ''),
            'event_time' => now(),
        ]);

        // ---- JALUR TEXT ----
        if ($type === 'text' && $text !== '') {
            return $this->handleText($phone, $text);
        }

        // ---- JALUR FOTO ----
        if ($type === 'photo' && $request->hasFile('photo')) {
            return $this->handlePhoto($phone, $request->file('photo'));
        }

        return response()->json(['status' => 'ignored', 'message' => 'Tidak ada konten dikenali']);
    }

    private function handleText(string $phone, string $text)
    {
        // Aktivasi: AKTIVASI-PTA-XXXX
        if (preg_match('/^AKTIVASI[-_ ]?(PTA-\d+)$/i', $text, $m)) {
            $customer = Customer::where('customer_id', strtoupper($m[1]))->first();
            if (!$customer) {
                WhatsAppService::send($phone, "Maaf, ID *{$m[1]}* tidak ditemukan. Hubungi operator desa.");
                return response()->json(['status' => 'error', 'message' => 'Customer not found']);
            }

            $customer->lid = $phone;
            $customer->save();
            WhatsAppService::send($phone, "✅ Aktivasi berhasil, *{$customer->name}*\nFitur kirim FOTO meteran kini aktif. Terima kasih.");
            EventLog::create(['device_id' => 0, 'event_type' => 'WA Aktivasi', 'message' => "{$customer->customer_id} diaktifkan via LID {$phone}", 'event_time' => now()]);

            return response()->json(['status' => 'activated']);
        }

        // Nomor belum aktivasi?
        if (!Customer::where('lid', $phone)->exists()) {
            WhatsAppService::send($phone, "Halo! Untuk melapor meteran air, silakan kirim pesan *AKTIVASI-PTA-XXXX* (ganti XXXX sesuai ID Anda).");
        }

        return response()->json(['status' => 'ok']);
    }

    private function handlePhoto(string $phone, $file)
    {
        $customer = Customer::where('lid', $phone)->first();
        if (!$customer) {
            return response()->json(['status' => 'error', 'message' => 'Belum aktivasi'], 403);
        }

        // Simpan foto: meter_photos/Y/m/sessionid.jpg
        $ym = now()->format('Y/m');
        $sessionId = 'sess-' . uniqid() . '-' . str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $path = $ym . '/' . $sessionId . '.jpg';

        Storage::disk('local')->put('meter_photos/' . $path, file_get_contents($file->getRealPath()));

        CustomerValidation::create([
            'session_id' => $sessionId,
            'lid' => $customer->lid,
            'phone' => $customer->phone ?? $phone,
            'angka_sementara' => 0,
            'foto_path' => $path,
            'status' => 'MENUNGGU',
        ]);

        EventLog::create(['device_id' => 0, 'event_type' => 'WA Foto', 'message' => "Foto {$customer->customer_id} masuk antrean {$sessionId}", 'event_time' => now()]);

        WhatsAppService::send($phone, "📸 Foto meteran Anda telah diterima (*{$customer->name}*). Terima kasih, petugas akan melakukan validasi.");

        return response()->json(['status' => 'ok', 'session_id' => $sessionId]);
    }
}