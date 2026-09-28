<?php

use App\Http\Controllers\AdminLogController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\DeviceApiController;
use App\Http\Controllers\Api\LogApiController;
use App\Http\Controllers\Api\SystemApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\EventLogController;
use App\Http\Controllers\MeterController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PumpLogController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SensorLogController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Auth — POST /login dibatasi 5 percobaan per menit per IP (anti brute-force, audit High 2.2)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/logout', [AuthController::class, 'logout']);

Route::middleware('auth.session')->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Devices
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/register', [DeviceController::class, 'create'])->name('devices.create');
    Route::post('/devices/register', [DeviceController::class, 'store'])->name('devices.store');
    Route::get('/devices/show/{id}', [DeviceController::class, 'show'])->name('devices.show');
    Route::get('/devices/edit/{id}', [DeviceController::class, 'edit'])->name('devices.edit');
    Route::post('/devices/update/{id}', [DeviceController::class, 'update'])->name('devices.update');
    Route::post('/devices/delete/{id}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    Route::post('/devices/apply/{id}', [DeviceController::class, 'applySettings'])->name('devices.apply');
    Route::post('/devices/sync/{id}', [DeviceController::class, 'syncWithMasterData'])->name('devices.sync');
    Route::get('/devices/detected', [DeviceController::class, 'detected'])->name('devices.detected');
    Route::post('/devices/detected/{id}/delete', [DeviceController::class, 'destroyDetected'])->name('devices.detected.delete');

    // Kontrol perangkat dari kartu dashboard (session web + CSRF)
    Route::post('/api/device-command', [DeviceApiController::class, 'command'])->name('devices.command');

    // Data UI monitoring (sebelumnya publik di routes/api.php → wajib login, audit Critical 1.2/1.3).
    // URI sengaja dipertahankan sama agar fetch() dari dashboard & halaman detail tetap jalan
    // (cookie sesi ikut terkirim otomatis). POST tetap dilindungi CSRF grup web.
    Route::get('/api/dashboard/data', [DashboardApiController::class, 'data'])->name('api.dashboard.data');
    Route::get('/api/dashboard-data', [DashboardApiController::class, 'data'])->name('api.dashboard.data.short');
    Route::get('/api/device/history', [DashboardApiController::class, 'history'])->name('api.device.history');
    Route::get('/api/system/detected-devices', [SystemApiController::class, 'detectedDevices'])->name('api.detected-devices');
    Route::get('/api/detected-devices', [SystemApiController::class, 'detectedDevices'])->name('api.detected-devices.short');
    Route::get('/api/terminal/events', [LogApiController::class, 'terminalEvents'])->name('api.terminal.events');
    Route::post('/api/terminal/clear', [LogApiController::class, 'clearTerminalEvents'])->name('api.terminal.clear');
    Route::get('/api/meter/last/{customerId}', [DashboardApiController::class, 'lastReading'])->name('api.meter.last');

    // Monitoring
    Route::get('/monitoring', [MonitoringController::class, 'overview'])->name('monitoring.overview');
    Route::get('/monitoring/system', [MonitoringController::class, 'system'])->name('monitoring.system');
    Route::get('/monitoring/database', [MonitoringController::class, 'database'])->name('monitoring.database');
    Route::get('/monitoring/performance', [MonitoringController::class, 'performance'])->name('monitoring.performance');

    // Settings: Tangki / Pompa / Sensor / Tarif / Tampilan
    Route::get('/settings/tanks', [SettingController::class, 'tanks'])->name('settings.tanks');
    Route::post('/settings/tanks', [SettingController::class, 'storeTank']);
    Route::post('/settings/tanks/{id}', [SettingController::class, 'updateTank']);
    Route::post('/settings/tanks/{id}/delete', [SettingController::class, 'destroyTank']);

    Route::get('/settings/pumps', [SettingController::class, 'pumps'])->name('settings.pumps');
    Route::post('/settings/pumps', [SettingController::class, 'storePump']);
    Route::post('/settings/pumps/{id}', [SettingController::class, 'updatePump']);
    Route::post('/settings/pumps/{id}/delete', [SettingController::class, 'destroyPump']);

    Route::get('/settings/sensors', [SettingController::class, 'sensors'])->name('settings.sensors');
    Route::post('/settings/sensors', [SettingController::class, 'storeSensor']);
    Route::post('/settings/sensors/{id}', [SettingController::class, 'updateSensor']);
    Route::post('/settings/sensors/{id}/delete', [SettingController::class, 'destroySensor']);

    Route::get('/settings/tariff', [SettingController::class, 'tariff'])->name('settings.tariff');
    Route::post('/settings/tariff', [SettingController::class, 'updateTariff']);
    Route::get('/settings/display', [SettingController::class, 'display'])->name('settings.display');
    Route::post('/settings/display', [SettingController::class, 'updateDisplay']);

    // Templates (gauge)
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::post('/templates/{id}', [TemplateController::class, 'update'])->name('templates.update');
    Route::post('/templates/{id}/delete', [TemplateController::class, 'destroy'])->name('templates.destroy');
    Route::post('/templates/{id}/activate', [TemplateController::class, 'activate'])->name('templates.activate');

    // Pelanggan
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers/store', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/edit/{id}', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::post('/customers/update/{id}', [CustomerController::class, 'update'])->name('customers.update');
    Route::post('/customers/delete/{id}', [CustomerController::class, 'destroy'])->name('customers.delete');
    Route::get('/customers/export', [CustomerController::class, 'export'])->name('customers.export');
    Route::post('/customers/import', [CustomerController::class, 'import'])->name('customers.import');
    Route::post('/customers/broadcast', [CustomerController::class, 'broadcastRequest'])->name('customers.broadcast');
    Route::get('/customers/broadcast-history', [CustomerController::class, 'broadcastHistory'])->name('customers.broadcast-history');

    // Kasir: Meter
    Route::get('/meter', [MeterController::class, 'index'])->name('meter.index');
    Route::post('/meter/store', [MeterController::class, 'store'])->name('meter.store');
    Route::get('/meter/validation-queue', [MeterController::class, 'validationQueue'])->name('meter.validation-queue');
    Route::post('/meter/admin-validate', [MeterController::class, 'adminValidate'])->name('meter.admin-validate');
    Route::post('/meter/bulk-validate', [MeterController::class, 'bulkValidate'])->name('meter.bulk-validate');
    Route::post('/meter/delete/{id}', [MeterController::class, 'delete'])->name('meter.delete');
    Route::post('/meter/delete-pending/{sessionId}', [MeterController::class, 'deletePending'])->name('meter.delete-pending');
    Route::post('/meter/delete-all-pending', [MeterController::class, 'deleteAllPending'])->name('meter.delete-all-pending');
    Route::post('/meter/send-wa', [MeterController::class, 'sendDirectMessage'])->name('meter.send-wa');
    Route::get('/meter/last-reading/{id}', [MeterController::class, 'getLastReading'])->name('meter.last-reading');
    Route::get('/meter/meter-report', [MeterController::class, 'meterReport'])->name('meter.meter-report');
    Route::post('/meter/send-bill/{id}', [MeterController::class, 'sendBillWA'])->name('meter.send-bill');
    Route::post('/meter/send-mass-bill', [MeterController::class, 'sendMassBill'])->name('meter.send-mass-bill');

    // Pembayaran
    Route::get('/payment', [PaymentController::class, 'index'])->name('payment.index');
    Route::get('/payment/verifiedBills', [PaymentController::class, 'verifiedBills'])->name('payment.verified-bills');
    Route::get('/payment/getBill/{id}', [PaymentController::class, 'getBill'])->name('payment.get-bill');
    Route::get('/payment/getReceipt/{id}', [PaymentController::class, 'getReceipt'])->name('payment.get-receipt');
    Route::post('/payment/store', [PaymentController::class, 'store'])->name('payment.store');

    // Logs
    Route::get('/logs/pumps', [PumpLogController::class, 'index'])->name('logs.pumps');
    Route::get('/logs/sensors', [SensorLogController::class, 'index'])->name('logs.sensors');
    Route::get('/logs/admin', [AdminLogController::class, 'index'])->name('logs.admin');
    Route::get('/logs/events', [EventLogController::class, 'index'])->name('logs.events');

    // Foto meteran (dari storage, diproteksi login)
    Route::get('/meter-photos/{path}', function (string $path) {
        $full = realpath(storage_path('app/meter_photos/' . $path));
        $base = realpath(storage_path('app/meter_photos'));
        if (!$full || !$base || !str_starts_with($full, $base) || !is_file($full)) {
            abort(404);
        }
        return response()->file($full);
    })->where('path', '.*')->name('meter.photos');

    // Users (admin only)
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users/store', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/edit/{id}', [UserController::class, 'edit'])->name('users.edit');
    Route::post('/users/update/{id}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/delete/{id}', [UserController::class, 'destroy'])->name('users.delete');
});

