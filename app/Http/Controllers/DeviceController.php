<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\EventLog;
use App\Models\DetectedDevice;
use App\Models\GaugeTemplate;
use App\Models\IndicatorSetting;
use App\Models\Pump;
use App\Models\PumpLog;
use App\Models\Sensor;
use App\Models\SensorLog;
use App\Models\Tank;
use App\Support\Permission;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    private function check(string $action = 'read'): void
    {
        Permission::abortUnlessCan(session('user.role', 'Viewer'), 'devices', $action);
    }

    public function index()
    {
        $this->check();
        $devices = Device::with(['tank', 'pump', 'sensor'])->get();
        $detected = DetectedDevice::orderByDesc('last_seen')->get();
        return view('devices.index', ['devices' => $devices, 'detected' => $detected]);
    }

    public function create(Request $request)
    {
        $this->check('create');
        return view('devices.register', [
            'tanks' => Tank::all(),
            'pumps' => Pump::all(),
            'sensors' => Sensor::all(),
            'prefillMac' => strtoupper(trim((string) $request->query('mac', ''))),
        ]);
    }

    public function store(Request $request)
    {
        $this->check('create');
        $data = $request->validate([
            'mac_address' => 'required|string|size:17|unique:devices,mac_address',
            'device_type' => 'required|in:MONITOR,ACTUATOR',
            'tank_id' => 'required|exists:tank_configurations,id',
            'pump_id' => 'required|exists:pumps,id',
            'sensor_id' => 'nullable|exists:sensors,id',
            'full_tank_distance' => 'nullable|integer|min:1',
            'empty_tank_distance' => 'nullable|integer|min:1',
            'trigger_percentage' => 'nullable|integer|min:1|max:100',
            'control_mode' => 'required|in:AUTO,MANUAL,TIMED',
        ]);

        $data['mac_address'] = strtoupper($data['mac_address']);
        $device = Device::create($data + ['status' => 'OFF']);
        $device = $this->fillFromMasterData($device);
        EventLog::create([
            'device_id' => $device->id,
            'event_type' => 'Device',
            'message' => "Perangkat {$device->mac_address} terdaftar",
            'event_time' => now(),
        ]);
        DetectedDevice::where('mac_address', $device->mac_address)->delete();

        return redirect()->route('devices.index')->with('success', 'Perangkat berhasil didaftarkan.');
    }

    public function show(int $id)
    {
        $this->check();
        $device = Device::with(['tank', 'pump', 'sensor'])->findOrFail($id);

        // Level air terakhir (interlock satu bak: ACTUATOR memakai data MONITOR pasangan)
        $sourceId = $device->id;
        if ($device->device_type !== 'MONITOR' && $device->tank_id) {
            $monId = Device::where('tank_id', $device->tank_id)
                ->where('device_type', 'MONITOR')->value('id');
            if ($monId) $sourceId = (int) $monId;
        }
        $lastLog = SensorLog::where('device_id', $sourceId)->orderByDesc('record_time')->first();

        // Statistik pompa 24 jam (siklus nyala + durasi) — pola sistem lama
        $since24 = now()->subDay();
        $logs24 = PumpLog::where('device_id', $id)
            ->where('timestamp', '>=', $since24)->orderBy('timestamp')->get();
        $cycles = 0; $totalSec = 0; $onAt = null; $prev = null;
        foreach ($logs24 as $l) {
            if ($l->pump_status === 'ON') {
                if ($prev !== 'ON') $cycles++;
                if ($onAt === null) $onAt = $l->timestamp->timestamp;
            } elseif ($onAt !== null) {
                $totalSec += max(0, $l->timestamp->timestamp - $onAt);
                $onAt = null;
            }
            $prev = $l->pump_status;
        }
        if ($onAt !== null) {
            // Saat offline, blok ON terakhir ditutup di kontak terakhir (jam offline jangan dihitung nyala)
            $endAt = ($device->isOnline() || ! $device->last_update)
                ? now()->timestamp
                : (int) $device->last_update->timestamp;
            $totalSec += max(0, $endAt - $onAt);
        }

        // Waktu transisi status pompa terakhir (badge timer durasi nyala/mati, count-up)
        $pumpStatusSince = null;
        if (in_array($device->status, ['ON', 'OFF'], true)) {
            $lastAt = PumpLog::where('device_id', $id)
                ->where('pump_status', $device->status)->orderByDesc('timestamp')->value('timestamp');
            if ($lastAt) {
                $pumpStatusSince = ($lastAt instanceof \DateTimeInterface ? $lastAt : \Carbon\Carbon::parse($lastAt))->timestamp;
            }
        }

        // Log koneksi: saat perangkat offline, catat "Koneksi terputus"
        // dengan jangkar kontak terakhir (idempoten — lihat EventLog::logDisconnect)
        // supaya langsung muncul di "Log Kejadian Terakhir" begitu halaman dibuka.
        if (! $device->isOnline() && $device->last_update) {
            EventLog::logDisconnect((int) $device->id, $device->last_update);
        }

        return view('devices.show', [
            'device' => $device,
            'latestWaterPct' => (float) ($lastLog->water_percentage ?? 0),
            'pumpStatusSince' => $pumpStatusSince,
            'pumpStatus' => $device->status,
            'pump24' => [
                'cycles' => $cycles,
                'cycle_count' => $cycles,
                'seconds' => $totalSec,
                'formatted' => sprintf('%02d:%02d', intdiv($totalSec, 3600), intdiv($totalSec % 3600, 60)),
            ],
            'sensorLogs' => SensorLog::where('device_id', $id)->orderByDesc('record_time')->limit(50)->get(),
            'pumpLogs' => PumpLog::where('device_id', $id)->orderByDesc('timestamp')->limit(50)->get(),
            'eventLogs' => EventLog::where('device_id', $id)->orderByDesc('event_time')->limit(20)->get(),
            'gaugeTemplate' => $this->resolveGaugeTemplate(),
            'indicator_settings' => IndicatorSetting::getSettings(),
        ]);
    }

    /**
     * Template gauge aktif (fallback ke template inti) — pola resolveCoreTemplate()
     * sistem lama agar halaman detail tetap menampilkan gauge walau id template
     * di pengaturan tidak lagi cocok.
     */
    private function resolveGaugeTemplate(): ?GaugeTemplate
    {
        $activeId = IndicatorSetting::getSettings()['active_template_id'] ?? null;

        return ($activeId ? GaugeTemplate::where('name', $activeId)->first() : null)
            ?: GaugeTemplate::where('is_core', true)->orderBy('id')->first()
            ?: GaugeTemplate::orderBy('id')->first();
    }

    public function edit(int $id)
    {
        $this->check('update');
        return view('devices.edit', [
            'device' => Device::findOrFail($id),
            'tanks' => Tank::all(),
            'pumps' => Pump::all(),
            'sensors' => Sensor::all(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $this->check('update');
        $device = Device::findOrFail($id);
        $data = $request->validate([
            'device_type' => 'required|in:MONITOR,ACTUATOR',
            'tank_id' => 'required|exists:tank_configurations,id',
            'pump_id' => 'required|exists:pumps,id',
            'sensor_id' => 'nullable|exists:sensors,id',
            'full_tank_distance' => 'nullable|integer|min:1',
            'empty_tank_distance' => 'nullable|integer|min:1',
            'trigger_percentage' => 'nullable|integer|min:1|max:100',
            'control_mode' => 'required|in:AUTO,MANUAL,TIMED',
            'report_interval' => 'nullable|integer|min:1',
        ]);

        $device->fill($data);
        // Tandai agar firmware menarik konfigurasi baru pada poll berikutnya
        $device->config_update_command = true;
        $device->mode_update_command = true;
        $device->save();

        return redirect()->route('devices.index')->with('success', 'Konfigurasi perangkat diperbarui.');
    }

    public function destroy(int $id)
    {
        $this->check('delete');
        Device::findOrFail($id)->delete();
        return redirect()->route('devices.index')->with('success', 'Perangkat dihapus.');
    }

    public function applySettings(int $id)
    {
        $this->check('update');
        $device = Device::findOrFail($id);
        $device->config_update_command = true;
        $device->save();
        return back()->with('success', 'Perintah terapkan konfigurasi dikirim ke perangkat.');
    }

    public function syncWithMasterData(int $id)
    {
        $this->check('update');
        $device = Device::with(['tank', 'pump', 'sensor'])->findOrFail($id);
        $this->fillFromMasterData($device);
        $device->config_update_command = true;
        $device->save();

        return back()->with('success', 'Perangkat disinkronkan dengan data master.');
    }

    /**
     * Ambil parameter perangkat otomatis dari master data
     * (tangki = tinggi; sensor = jarak penuh & trigger; pompa = durasi ON/OFF).
     */
    private function fillFromMasterData(Device $device): Device
    {
        if ($device->tank) {
            $device->empty_tank_distance = (int) $device->tank->height;
        }
        if ($device->sensor) {
            $device->full_tank_distance = $device->sensor->full_tank_distance;
            $device->trigger_percentage = $device->sensor->trigger_percentage;
        }
        if ($device->pump) {
            $device->on_duration = (int) ceil($device->pump->on_duration_seconds / 60);
            $device->off_duration = (int) ceil($device->pump->off_duration_seconds / 60);
        }
        $device->save();
        return $device;
    }

    public function detected()
    {
        $this->check();
        return view('devices.detected', ['detected' => DetectedDevice::orderByDesc('last_seen')->get()]);
    }

    public function destroyDetected(int $id)
    {
        $this->check('delete');
        DetectedDevice::findOrFail($id)->delete();
        return back()->with('success', 'Entri perangkat terdeteksi dihapus.');
    }
}
