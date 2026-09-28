<?php

use App\Http\Controllers\Api\DeviceApiController;
use App\Http\Controllers\Api\SystemApiController;
use App\Http\Controllers\Api\TemplateApiController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Endpoint perangkat IoT (ESP8266) & UI
|--------------------------------------------------------------------------
| CATATAN Laravel 12: file ini OTOMATIS ber-prefix /api.
| Jadi Route::post('/log') = POST /api/log. JANGAN tulis '/api/log'
| di sini (itu akan menjadi /api/api/log → 404 di ESP).
|
| Endpoint /api/* untuk perangkat memakai middleware 'device.api'
| (menerima firmware lama tanpa key) — endpoint maintenance yang
| merusak data memakai 'device.key' (X-API-KEY wajib valid).
|
| Endpoint DATA UI (/api/dashboard/data, /api/dashboard-data,
| /api/device/history, /api/system/detected-devices,
| /api/terminal/{events,clear}, /api/meter/last/{id}) TIDAK lagi di
| sini — dipindahkan ke routes/web.php grup 'auth.session' supaya
| wajib login (temuan audit Critical 1.2 / 1.3).
*/

// --- WEBHOOK WHATSAPP (dari WA Gateway Node.js) → POST /api/api_wa ---
// (Nama route lama dipertahankan agar gateway existing tidak perlu diubah.)
Route::post('/api_wa', [WhatsAppWebhookController::class, 'handle'])->name('api_wa');
// Alias pendek: POST /api/wa
Route::post('/wa', [WhatsAppWebhookController::class, 'handle'])->name('api_wa.short');
// Alias tanpa prefix api/: POST /webhook/wa
Route::post('/webhook/wa', [WhatsAppWebhookController::class, 'handle'])->name('wa.webhook');

// --- CORE IoT DEVICE ENDPOINTS ---
Route::middleware('device.api')->group(function () {
    Route::post('/log', [DeviceApiController::class, 'log']);
    Route::get('/status', [DeviceApiController::class, 'status']);
    Route::post('/update', [DeviceApiController::class, 'update']);
    Route::post('/device/update', [DeviceApiController::class, 'update']);
    Route::post('/health', [DeviceApiController::class, 'health']);
    Route::post('/log-offline', [DeviceApiController::class, 'logOffline']);
});

// --- ALIAS LEGACY TANPA PREFIX /api (firmware lama: GET http://IP/api-status) ---
// Route ini didaftarkan di routes/web.php agar tidak kena prefix /api.
// (Lihat routes/web.php bagian "LEGACY IoT ALIASES".)

// --- FINGERPRINT SSL ---
// Dipakai firmware (Network_SSL.ino) sebagai handshake awal untuk client.setFingerprint()
// → tetap publik (firmware tidak punya sesi). Respons = SHA1 sertifikat (plain text).
// Karena setiap permintaan melakukan koneksi TLS keluar, dibatasi 30 permintaan/menit per IP.
Route::get('/fingerprint', [SystemApiController::class, 'fingerprint'])->middleware('throttle:30,1');

// --- MAINTENANCE (MERUSAK DATA) → WAJIB X-API-KEY VALID ---
// middleware 'device.key' TIDAK memberi jalur bebas untuk firmware lama.
// Bila dipanggil cron di server, sertakan key: ?api_key=<DEVICE_API_KEY>
// atau header X-API-KEY.
Route::middleware('device.key')->group(function () {
    Route::get('/system/cleanup', [SystemApiController::class, 'cleanupLogs']);
});

// --- DATA UI (dashboard/detail/terminal/meter) ---
// Pindah ke routes/web.php grup 'auth.session' — wajib login (audit Critical 1.2/1.3).
// GET /api/dashboard/data, /api/dashboard-data, /api/device/history,
// /api/system/detected-devices, /api/detected-devices, /api/terminal/events,
// POST /api/terminal/clear, GET /api/meter/last/{id}.

// --- TEMPLATE PREVIEW ---
Route::get('/template/preview/{id}', [TemplateApiController::class, 'preview']);

