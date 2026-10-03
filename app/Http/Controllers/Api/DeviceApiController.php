<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\EventLog;
use App\Models\PumpLog;
use App\Models\SensorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint untuk perangkat ESP8266 (dilindungi X-API-KEY).
 * Port dari DeviceApiController sistem asli.
 *
 * INTERLOCK MONITOR <-> ACTUATOR (satu tangki / "bak yang sama"):
 * - MONITOR (punya sensor) mengirim level air via /api/log.
 * - ACTUATOR (tanpa sensor) mengambil water_percentage dari log MONITOR
 *   pasangannya (satu tank_id) dan menjalankan kontrol pompa AUTO berdasarkan
 *   data tersebut — persis Water Priority sistem lama.
 */
class DeviceApiController extends Controller
{
    /**
     * Cari MONITOR pasangan untuk satu tangki (bukan dirinya sendiri).
     * Kunci interlock = tank_id (satu bak).
     */
    private function tankMonitor(Device $device): ?Device
    {
        if ($device->device_type === 'MONITOR') {
            return null;
        }
        return Device::where('tank_id', $device->tank_id)
            ->where('device_type', 'MONITOR')
            ->where('id', '!=', $device->id)
            ->first();
    }

    /**
     * Resolusi data level air untuk sebuah perangkat:
     * - MONITOR  → log sensor miliknya sendiri.
     * - ACTUATOR → log sensor MONITOR pasangan satu tangkinya (fallback: lognya sendiri).
     *
     * @return array{pct: float, cm: float, monitor: ?Device}
     */
    private function resolveWaterInfo(Device $device): array
    {
        $source = $device;
        if ($device->device_type !== 'MONITOR') {
            $source = $this->tankMonitor($device) ?? $device;
        }
        $lastLog = SensorLog::where('device_id', $source->id)
            ->latest('record_time')
            ->first();

        return [
            'pct' => (float) ($lastLog->water_percentage ?? 0),
            'cm' => (float) ($lastLog->water_level ?? 0),
            'monitor' => $source->device_type === 'MONITOR' && $source->id !== $device->id ? $source : null,
        ];
    }

    /**
     * Kontrol pompa AUTO berdasarkan persentase (pct dari MONITOR satu tangki).
     *
     * PERBAIKAN 3 Okt 2026 — "grafik tidak menunjukkan istirahat 10 menit":
     * Pada mode AUTO, PEMEGANG KENDALI adalah perangkat (firmware menjalankan logika
     * level + masa istirahat mesin `off_duration` sendiri). Port Laravel sebelumnya
     * menulis ulang `status` + `pump_logs` dari data level pada SETIAP poll
     * `/api/status` (3 detik) sehingga ~4 detik setelah perangkat melaporkan OFF
     * (safety cut-off) server menulis ON lagi. Akibatnya jeda istirahat 10 menit
     * hilang dari riwayat/grafik (durasi nyala tampak ~40 menit, bukan 30+10) dan
     * badge timer memakai waktu transisi yang salah.
     *
     * Sistem lama (`backup_pamsimas/app/Controllers/Api/DeviceApiController.php`)
     * pun hanya mengubah `status` dari laporan perangkat — server tidak pernah
     * menimpanya. Fungsi ini sekarang hanya MENGHITUNG perintah usulan
     * (`pump_command`) dengan ambang yang sama seperti firmware:
     * ON bila `pct <= trigger_percentage`, OFF bila `pct >= 98`.
     * Perubahan `status` di DB hanya terjadi lewat `/api/update` action `set_status`.
     *
     * @return string perintah usulan (ON/OFF) — TIDAK mengubah status di DB
     */
    private function applyAutoControl(Device $actuator, float $pct): string
    {
        if ($actuator->control_mode !== 'AUTO') {
            return $actuator->status;
        }
        if ($pct <= (int) ($actuator->trigger_percentage ?? 80)) {
            return 'ON';
        }
        if ($pct >= 98) {
            return 'OFF';
        }
        return $actuator->status;
    }

    /**
     * POST /api/log — perangkat kirim data level air.
     */
    public function log(Request $request)
    {
        // Firmware lama: {mac|mac_address, cm|water_level_cm, pct|water_percentage, rssi}
        $data = array_change_key_case($request->all(), CASE_LOWER);
        $mac = strtoupper(trim((string) ($data['mac'] ?? $data['mac_address'] ?? '')));
        if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid data'], 400);
        }

        $device = Device::where('mac_address', $mac)->first();
        if (!$device) {
            // Respons lama: suruh perangkat reboot & tunggu registrasi.
            return response()->json([
                'status' => 'unregistered',
                'restart_command' => 1,
                'server_time' => time(),
            ]);
        }

        $level = (float) ($data['cm'] ?? $data['water_level_cm'] ?? $data['water_level'] ?? 0);
        $pct = (float) ($data['pct'] ?? $data['water_percentage'] ?? 0);
        $rssi = (int) ($data['rssi'] ?? 0);

        SensorLog::create([
            'device_id' => $device->id,
            'water_level' => $level,
            'water_percentage' => $pct,
            'rssi' => $rssi,
            'record_time' => now(),
        ]);

        $device->last_update = now();
        $device->rssi = $rssi;
        if (isset($data['uptime'])) $device->uptime = (int) $data['uptime'];
        if (isset($data['free_heap'])) $device->free_heap = (int) $data['free_heap'];

        // Interlock satu bak: MONITOR yang mengirim data level → kontrol
        // ACTUATOR pasangannya (tank_id sama) secara langsung.
        if ($device->device_type === 'MONITOR') {
            foreach (Device::where('tank_id', $device->tank_id)
                ->where('device_type', 'ACTUATOR')
                ->where('id', '!=', $device->id)
                ->get() as $actuator) {
                $this->applyAutoControl($actuator, $pct);
            }
        } else {
            // ACTUATOR yang punya sensor lokal sendiri → kontrol dirinya.
            $this->applyAutoControl($device, $pct);
        }

        $this->aggregate($device->id, $pct);

        return response()->json([
            'status' => 'success',
            'pump_command' => $device->device_type === 'ACTUATOR' ? $device->status : null,
            'control_mode' => $device->control_mode,
        ]);
    }

    /**
     * GET /api/status?mac_address=XX (alias: ?mac=XX) — firmware menarik konfigurasi & perintah.
     * Mendukung ESP lama yang hanya mengirim ?mac= tanpa header API key.
     */
    public function status(Request $request)
    {
        $raw = $request->query('mac_address', $request->query('mac', ''));
        $mac = strtoupper(trim((string) $raw));
        if ($mac === '') {
            return response()->json(['status' => 'error', 'message' => 'mac_address wajib diisi'], 422);
        }
        $device = Device::where('mac_address', $mac)->first();
        if (!$device) {
            return response()->json([
                'status' => 'error',
                'message' => 'Perangkat belum terdaftar — hubungi admin untuk registrasi',
                'mac_address' => $mac,
            ], 404);
        }

        $device->last_update = now();
        if ($request->filled('rssi')) $device->rssi = (int) $request->query('rssi');
        $device->save();

        // Resolusi level air: ACTUATOR mengambil data dari MONITOR satu tangki.
        $water = $this->resolveWaterInfo($device);

        // Response lengkap ALA SISTEM LAMA — key tertentu WAJIB ada agar
        // firmware ESP8266 tidak crash (Exception 28 saat deserialisasi JSON).
        $response = [
            'id' => (int) $device->id,
            'status' => $device->status,
            'device_mode' => $device->device_type === 'MONITOR' ? 1 : 0,
            'control_mode' => $device->control_mode,
            'water_percentage' => $water['pct'],
            // source_ready selalu 1 (Water Priority): pompa tetap bisa
            // dikendalikan meski monitor hilang (recovery mode di sisi firmware).
            'source_ready' => 1,
            'mode_update_command' => (int) $device->mode_update_command,
            'config_update_command' => (int) $device->config_update_command,
            'restart_command' => (int) $device->restart_command,
            // Perangkat fisik membutuhkan satuan DETIK untuk timer internalnya
            // (nilai di DB sistem baru disimpan dalam MENIT).
            'on_duration' => (int) ($device->on_duration ?: 5) * 60,
            'off_duration' => (int) ($device->off_duration ?: 15) * 60,
            'delay_seconds' => (int) ($device->delay_seconds ?? 0),
            'full_tank_distance' => (int) $device->full_tank_distance,
            'empty_tank_distance' => (int) $device->empty_tank_distance,
            'trigger_percentage' => (int) ($device->trigger_percentage ?? 80),
            'min_run_time' => (int) ($device->min_run_time ?? 60),
            'sensor_debounce' => (int) ($device->sensor_debounce ?? 5),
            'report_interval' => (int) ($device->report_interval ?? 3),
            'server_time' => time(),
            // Alias untuk sistem baru (dashboard/JS).
            'pump_command' => null,
            'restart' => false,
            'config_update' => false,
        ];

        // ACTUATOR AUTO: perintah pompa dihitung dari data MONITOR satu tangki.
        if ($device->device_type !== 'MONITOR') {
            $response['pump_command'] = $this->applyAutoControl($device, $water['pct']);
            $response['status'] = $device->status;
        } elseif ($device->control_mode === 'MANUAL') {
            $response['pump_command'] = $device->status;
        }

        // Restart: tetap di-reset di sisi server saat dikirim (one-shot) — kalau tidak,
        // perangkat akan reboot berulang ketika ack-nya hilang di link yang buruk.
        if ($device->restart_command) {
            $response['restart'] = true;
            $device->restart_command = false;
        }

        // Config & mode: JANGAN diturunkan di sini. Flag baru turun ketika perangkat
        // meng-ack lewat /api/update (reset_config / reset_mode_update), sehingga
        // perubahan dari dashboard tidak hilang bila satu kali kirim gagal —
        // perangkat terus menerima flag 1 sampai benar-benar menarik konfigurasi.
        $response['config_update'] = (bool) ($device->config_update_command || $device->mode_update_command);

        $device->save();

        return response()->json($response);
    }

    /**
     * POST /api/update — perintah & laporan perangkat.
     *
     * Format firmware asli (dari backup sistem lama):
     *   {mac, action, value}
     *   action: set_status (laporan pompa), set_mode, set_manual_status,
     *           report_version, report_event, set_pump,
     *           reset_config / reset_mode_update / reset_ota_update / reset_restart
     *           (ack firmware bahwa flag satu kali pakai sudah diterima)
     * Format baru (tanpa action): {mac_address, pump_status, control_mode}
     * Device tak dikenal dibalas {status: unregistered} (200) — seperti
     * sistem lama — agar firmware menunggu registrasi, bukan error.
     */
    public function update(Request $request)
    {
        $data = array_change_key_case($request->all(), CASE_LOWER);
        $mac = strtoupper(trim((string) ($data['mac'] ?? $data['mac_address'] ?? '')));
        if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid data or missing MAC'], 400);
        }

        $device = Device::where('mac_address', $mac)->first();
        if (!$device) {
            return response()->json(['status' => 'unregistered']);
        }

        $action = $data['action'] ?? null;
        $value = $data['value'] ?? null;
        $keyValid = (bool) $request->attributes->get('device_key_valid', false);

        if ($action !== null) {
            switch ($action) {
                // Perangkat (ACTUATOR) melaporkan status pompa hasil kontrolnya.
                case 'set_status':
                    $new = strtoupper((string) $value);
                    if (!in_array($new, ['ON', 'OFF'], true)) {
                        return response()->json(['status' => 'error', 'message' => 'Invalid value'], 400);
                    }
                    $changed = $device->status !== $new;
                    $device->status = $new;
                    $device->last_update = now();
                    $device->save();
                    if ($changed) {
                        $this->writePumpLog($device, $new, "Pompa {$new} ({$device->control_mode}) — laporan perangkat");
                    }
                    return response()->json(['status' => 'success']);

                // Perintah dari dashboard web (butuh session login ATAU API key).
                case 'set_mode':
                    if (!$request->user() && !$keyValid) {
                        return response()->json(['status' => 'error', 'message' => 'Sesi login telah berakhir. Silakan login kembali.'], 401);
                    }
                    $new = ($value === 'MANUAL') ? 'MANUAL' : 'AUTO';
                    if ($device->control_mode === $new) {
                        return response()->json(['status' => 'success', 'message' => 'Mode already set']);
                    }
                    $device->control_mode = $new;
                    $device->mode_update_command = true;
                    $device->config_update_command = true;
                    $device->last_update = now();
                    $device->save();
                    EventLog::create([
                        'device_id' => $device->id,
                        'event_type' => 'Mode',
                        'message' => "Mode diubah ke {$new}",
                        'event_time' => now(),
                    ]);
                    return response()->json(['status' => 'success']);

                case 'set_manual_status':
                    if (!$request->user() && !$keyValid) {
                        return response()->json(['status' => 'error', 'message' => 'Sesi login telah berakhir. Silakan login kembali.'], 401);
                    }
                    $new = strtoupper((string) $value);
                    if (!in_array($new, ['ON', 'OFF'], true)) {
                        return response()->json(['status' => 'error', 'message' => 'Invalid value'], 400);
                    }
                    $changed = $device->status !== $new;
                    $device->status = $new;
                    $device->config_update_command = true;
                    $device->last_update = now();
                    $device->save();
                    if ($changed) {
                        $this->writePumpLog($device, $new, "Pompa {$new} (MANUAL) — perintah dashboard");
                    }
                    return response()->json(['status' => 'success']);

                // Perangkat melaporkan versi firmware / event (boot, network_recovered, dll).
                case 'report_version':
                    $device->firmware_version = substr((string) $value, 0, 20);
                    $device->last_update = now();
                    $device->save();
                    return response()->json(['status' => 'success']);

                case 'report_event':
                    EventLog::create([
                        'device_id' => $device->id,
                        'event_type' => 'Info',
                        'message' => substr((string) $value, 0, 500),
                        'event_time' => now(),
                    ]);
                    return response()->json(['status' => 'success']);

                // Perintah ON/OFF pompa dari dashboard (wajib mode MANUAL — sama seperti sistem lama).
                case 'set_pump':
                    if (!$request->user() && !$keyValid) {
                        return response()->json(['status' => 'error', 'message' => 'Sesi login telah berakhir. Silakan login kembali.'], 401);
                    }
                    if ($device->control_mode !== 'MANUAL') {
                        return response()->json(['status' => 'error', 'message' => 'Perintah pompa hanya bisa dijalankan dalam mode MANUAL.'], 403);
                    }
                    $new = strtoupper((string) $value);
                    if (!in_array($new, ['ON', 'OFF'], true)) {
                        return response()->json(['status' => 'error', 'message' => 'Invalid value'], 400);
                    }
                    $changed = $device->status !== $new;
                    $device->status = $new;
                    $device->config_update_command = true;
                    $device->last_update = now();
                    $device->save();
                    if ($changed) {
                        $this->writePumpLog($device, $new, "Pompa {$new} (MANUAL) — perintah dashboard");
                    }
                    return response()->json(['status' => 'success', 'pump_command' => $new]);

                // Ack firmware setelah server mengirim flag one-shot.
                // Firmware (API_Communication.ino) mengirim: reset_config, reset_mode_update,
                // reset_ota_update, reset_restart — sebelumnya semuanya dibalas 400
                // "Unknown action" sehingga log serial perangkat penuh error.
                // Penanganan ini idempoten: flag yang sama juga sudah di-reset oleh status()
                // saat nilainya dikirim ke perangkat.
                case 'reset_config':
                    return $this->ackFirmwareFlag($device, $action, 'config_update_command', 'Perangkat menerapkan konfigurasi baru.');

                case 'reset_mode_update':
                    return $this->ackFirmwareFlag($device, $action, 'mode_update_command', 'Perangkat menerapkan perubahan mode.');

                case 'reset_restart':
                    return $this->ackFirmwareFlag($device, $action, 'restart_command', 'Perintah restart diterima perangkat.');

                case 'reset_ota_update':
                    // Fitur OTA belum punya kolom flag di skema baru → tetap dibalas 200
                    // agar firmware tidak menerima error 400.
                    return $this->ackFirmwareFlag($device, $action);

                default:
                    return response()->json(['status' => 'error', 'message' => "Unknown action: {$action}"], 400);
            }
        }

        // Format baru tanpa action: {mac_address, pump_status, control_mode, ...}
        if (isset($data['pump_status'])) {
            $new = strtoupper((string) $data['pump_status']);
            if (!in_array($new, ['ON', 'OFF'], true)) {
                return response()->json(['status' => 'error', 'message' => 'pump_status harus ON/OFF'], 422);
            }
            $changed = $device->status !== $new;
            $device->status = $new;
            if (isset($data['control_mode']) && in_array($data['control_mode'], ['AUTO', 'MANUAL', 'TIMED'], true)) {
                $device->control_mode = $data['control_mode'];
            }
            $device->last_update = now();
            $device->save();
            if ($changed) {
                $this->writePumpLog($device, $new, "Pompa {$new} ({$device->control_mode})");
            }
            return response()->json(['status' => 'success']);
        }

        // Tidak ada action & tidak ada pump_status → cukup catat last_seen.
        $device->last_update = now();
        $device->save();
        return response()->json(['status' => 'success']);
    }

    /**
     * POST /api/device-command — kontrol perangkat dari dashboard web (session + CSRF).
     * Dipakai tombol di kartu gauge: set_mode (AUTO/MANUAL) & set_pump (ON/OFF).
     */
    public function command(Request $request)
    {
        $data = $request->validate([
            'mac' => 'required|string',
            'action' => 'required|in:set_mode,set_pump',
            'value' => 'required|string',
        ]);

        $mac = strtoupper(trim($data['mac']));
        $device = Device::where('mac_address', $mac)->first();
        if (!$device) {
            return response()->json(['status' => 'error', 'message' => 'Perangkat tidak ditemukan.'], 404);
        }

        $value = (string) $data['value'];

        if ($data['action'] === 'set_mode') {
            $new = ($value === 'MANUAL') ? 'MANUAL' : 'AUTO';
            if ($device->control_mode === $new) {
                return response()->json(['status' => 'success', 'message' => 'Mode already set', 'control_mode' => $new]);
            }
            $device->control_mode = $new;
            $device->mode_update_command = true;
            $device->config_update_command = true;
            $device->save();
            EventLog::create([
                'device_id' => $device->id,
                'event_type' => 'Mode',
                'message' => "Mode diubah ke {$new} via dashboard",
                'event_time' => now(),
            ]);
            return response()->json(['status' => 'success', 'control_mode' => $new]);
        }

        // set_pump — sama seperti sistem lama: hanya boleh dalam mode MANUAL.
        if ($device->control_mode !== 'MANUAL') {
            return response()->json(['status' => 'error', 'message' => 'Perintah pompa hanya bisa dijalankan dalam mode MANUAL.'], 403);
        }
        $new = strtoupper($value);
        if (!in_array($new, ['ON', 'OFF'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid value'], 400);
        }
        $changed = $device->status !== $new;
        $device->status = $new;
        $device->config_update_command = true;
        $device->save();
        if ($changed) {
            $this->writePumpLog($device, $new, "Pompa {$new} (MANUAL) — perintah dashboard");
        }
        return response()->json(['status' => 'success', 'pump_command' => $new]);
    }

    /**
     * POST /api/health — telemetry kesehatan perangkat.
     */
    public function health(Request $request)
    {
        // Firmware lama mengirim: {mac, uptime, free_heap, rssi, reset_reason, fs_used, fs_total}
        $data = array_change_key_case($request->all(), CASE_LOWER);
        $mac = strtoupper(trim((string) ($data['mac'] ?? $data['mac_address'] ?? '')));
        if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid data or missing MAC for health check.'], 400);
        }

        $device = Device::where('mac_address', $mac)->first();
        if (!$device) {
            return response()->json(['status' => 'error', 'message' => 'Invalid data or missing MAC for health check.'], 400);
        }

        $device->last_update = now();
        $device->uptime = (int) ($data['uptime'] ?? $device->uptime);
        $device->free_heap = (int) ($data['free_heap'] ?? $device->free_heap);
        $device->reset_reason = $data['reset_reason'] ?? $device->reset_reason;
        $device->firmware_version = $data['firmware_version'] ?? $device->firmware_version;
        $device->firmware_build_date = $data['firmware_build_date'] ?? $device->firmware_build_date;
        if (isset($data['rssi'])) $device->rssi = (int) $data['rssi'];
        $device->save();

        // Peringatan kapasitas LittleFS (seperti sistem lama).
        if (isset($data['fs_used'], $data['fs_total']) && $data['fs_total'] > 0) {
            $percent = ((int) $data['fs_used'] / (int) $data['fs_total']) * 100;
            if ($percent > 90) {
                EventLog::create([
                    'device_id' => $device->id,
                    'event_type' => 'Warning',
                    'message' => 'LittleFS hampir penuh: ' . round($percent, 1) . '% digunakan.',
                    'event_time' => now(),
                ]);
            }
        }
        if (isset($data['fs_error']) && $data['fs_error']) {
            EventLog::create([
                'device_id' => $device->id,
                'event_type' => 'Warning',
                'message' => 'LittleFS terdeteksi rusak atau gagal dimount!',
                'event_time' => now(),
            ]);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * POST /api/log-offline — kirim log buffer saat perangkat offline.
     */
    public function logOffline(Request $request)
    {
        // Format firmware lama: {mac|mac_address, sensor_logs|logs:
        //   [[epoch, cm, pct, rssi], ...] (array of array)}
        // Format baru: {mac_address, logs: [{water_level, water_percentage, record_time}]}
        $data = array_change_key_case($request->all(), CASE_LOWER);
        $mac = strtoupper(trim((string) ($data['mac'] ?? $data['mac_address'] ?? '')));
        $logs = $data['logs'] ?? $data['sensor_logs'] ?? null;
        if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac) || !is_array($logs)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid data'], 400);
        }

        $device = Device::where('mac_address', $mac)->first();
        if (!$device) {
            return response()->json(['status' => 'error', 'message' => 'Perangkat belum terdaftar'], 404);
        }

        $count = 0;
        foreach (array_slice($logs, 0, 500) as $log) {
            if (is_array($log) && (isset($log['water_level']) || isset($log['water_percentage']))) {
                // Format objek (sistem baru)
                SensorLog::create([
                    'device_id' => $device->id,
                    'water_level' => (float) $log['water_level'],
                    'water_percentage' => (float) $log['water_percentage'],
                    'rssi' => $device->rssi,
                    'record_time' => $log['record_time'] ?? now()->toDateTimeString(),
                ]);
            } elseif (is_array($log)) {
                // Format array-of-array firmware lama: [epoch, cm, pct, rssi]
                $epoch = is_numeric($log[0] ?? null) ? (int) $log[0] : time();
                SensorLog::create([
                    'device_id' => $device->id,
                    'water_level' => (float) ($log[1] ?? 0),
                    'water_percentage' => (float) ($log[2] ?? 0),
                    'rssi' => (int) ($log[3] ?? $device->rssi),
                    'record_time' => date('Y-m-d H:i:s', $epoch),
                ]);
            }
            $count++;
        }

        $device->last_offline_sync = now();
        $device->last_update = now();
        $device->save();

        EventLog::create([
            'device_id' => $device->id,
            'event_type' => 'Sync',
            'message' => 'Sinkronisasi offline: ' . $count . ' log',
            'event_time' => now(),
        ]);

        return response()->json(['status' => 'success', 'received' => $count]);
    }

    /**
     * Balasan untuk ack flag firmware (reset_config / reset_mode_update /
     * reset_ota_update / reset_restart).
     *
     * Inilah SATU-SATUNYA tempat flag satu kali pakai diturunkan: flag baru dibersihkan
     * ketika perangkat benar-benar menerima/menerapkan perintah (ack), bukan saat
     * perintah dikirim — mencegah perubahan dari dashboard hilang di link yang buruk.
     * Setiap penurunan flag dicatat ke event_logs sebagai jejak "sudah diterapkan".
     */
    private function ackFirmwareFlag(Device $device, string $action, ?string $flagColumn = null, ?string $logMessage = null)
    {
        $wasPending = $flagColumn !== null && (int) $device->{$flagColumn} === 1;

        if ($wasPending) {
            $device->{$flagColumn} = false;
        }

        $device->last_update = now();
        $device->save();

        if ($wasPending && $logMessage !== null) {
            EventLog::create([
                'device_id' => $device->id,
                'event_type' => 'Info',
                'message' => $logMessage,
                'event_time' => now(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'action' => $action,
            'mode_update_command' => (int) $device->mode_update_command,
            'config_update_command' => (int) $device->config_update_command,
            'restart_command' => (int) $device->restart_command,
        ]);
    }

    private function writePumpLog(Device $device, string $status, string $message): void
    {
        PumpLog::create([
            'device_id' => $device->id,
            'pump_status' => $status,
            'control_mode' => $device->control_mode,
            'duration_seconds' => 0,
            'timestamp' => now(),
        ]);
        EventLog::create([
            'device_id' => $device->id,
            'event_type' => 'Pump',
            'message' => $message,
            'event_time' => now(),
        ]);
    }

    /**
     * Agregasi log per-menit & per-jam (upsert per kunci waktu).
     */
    private function aggregate(int $deviceId, float $pct): void
    {
        $minuteTs = now()->startOfMinute();
        DB::table('minute_sensor_logs')->updateOrInsert(
            ['device_id' => $deviceId, 'minute_timestamp' => $minuteTs->toDateTimeString()],
            ['avg_water_level' => $pct]
        );

        $hourTs = now()->startOfHour();
        $hourAvg = (float) (DB::table('minute_sensor_logs')
            ->where('device_id', $deviceId)
            ->where('minute_timestamp', '>=', $hourTs->toDateTimeString())
            ->avg('avg_water_level') ?? $pct);

        DB::table('hourly_sensor_logs')->updateOrInsert(
            ['device_id' => $deviceId, 'hour_timestamp' => $hourTs->toDateTimeString()],
            ['avg_water_level' => $hourAvg]
        );
    }
}
