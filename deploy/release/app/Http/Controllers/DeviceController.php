<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\EventLog;
use App\Models\DetectedDevice;
use App\Models\Pump;
use App\Models\Sensor;
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
        return view('devices.index', ['devices' => $devices]);
    }

    public function create()
    {
        $this->check('create');
        return view('devices.register', [
            'tanks' => Tank::all(),
            'pumps' => Pump::all(),
            'sensors' => Sensor::all(),
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
        return view('devices.show', [
            'device' => Device::with(['tank', 'pump', 'sensor'])->findOrFail($id),
            'sensorLogs' => Device::findOrFail($id)->sensorLogs()->orderByDesc('record_time')->limit(50)->get(),
            'pumpLogs' => Device::findOrFail($id)->pumpLogs()->orderByDesc('timestamp')->limit(50)->get(),
        ]);
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

        if ($device->tank && $device->empty_tank_distance != $device->tank->height) {
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
        $device->config_update_command = true;
        $device->save();

        return back()->with('success', 'Perangkat disinkronkan dengan data master.');
    }

    public function detected()
    {
        $this->check();
        return view('devices.detected', ['detected' => DetectedDevice::orderByDesc('last_seen')->get()]);
    }
}
