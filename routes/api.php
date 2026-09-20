<?php

use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\DeviceApiController;
use App\Http\Controllers\Api\LogApiController;
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
| Semua endpoint /api/* untuk perangkat dilindungi X-API-KEY
| via middleware 'device.api' (kecuali /status & /health yang
| publik agar firmware lama tanpa key tetap bisa konek).
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

// --- DASHBOARD & UI DATA ---
Route::get('/dashboard/data', [DashboardApiController::class, 'data']);
Route::get('/dashboard-data', [DashboardApiController::class, 'data']);
Route::get('/device/history', [DashboardApiController::class, 'history']);

// --- SYSTEM & MONITORING ---
Route::get('/system/detected-devices', [SystemApiController::class, 'detectedDevices']);
Route::get('/detected-devices', [SystemApiController::class, 'detectedDevices']);
Route::get('/fingerprint', [SystemApiController::class, 'fingerprint']);
Route::get('/system/cleanup', [SystemApiController::class, 'cleanupLogs']);

// --- LOGS & TERMINAL ---
Route::get('/terminal/events', [LogApiController::class, 'terminalEvents']);
Route::post('/terminal/clear', [LogApiController::class, 'clearTerminalEvents']);

// --- TEMPLATE PREVIEW ---
Route::get('/template/preview/{id}', [TemplateApiController::class, 'preview']);

// --- METER ---
Route::get('/meter/last/{customerId}', [DashboardApiController::class, 'lastReading']);

