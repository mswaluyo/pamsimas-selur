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
| Semua endpoint /api/* untuk perangkat dilindungi X-API-KEY.
*/

// --- WEBHOOK WHATSAPP (dari WA Gateway Node.js) ---
Route::post('/api_wa', [WhatsAppWebhookController::class, 'handle'])->name('api_wa');
// Alias tanpa prefix api/
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
