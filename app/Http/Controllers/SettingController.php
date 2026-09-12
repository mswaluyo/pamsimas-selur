<?php

namespace App\Http\Controllers;

use App\Models\IndicatorSetting;
use App\Models\Pump;
use App\Models\Sensor;
use App\Models\Tank;
use App\Support\Permission;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private function check(string $action = 'read'): void
    {
        Permission::abortUnlessCan(session('user.role', 'Viewer'), 'settings', $action);
    }

    // ---------- TANGKI ----------
    public function tanks()
    {
        $this->check();
        return view('settings.tanks', ['tanks' => Tank::withCount('devices')->get()]);
    }

    public function storeTank(Request $request)
    {
        $this->check('create');
        Tank::create($request->validate([
            'tank_name' => 'required|string|max:100',
            'tank_shape' => 'required|in:kotak,bulat',
            'height' => 'required|numeric|min:1',
        ]));
        return back()->with('success', 'Tangki ditambahkan.');
    }

    public function updateTank(Request $request, int $id)
    {
        $this->check('update');
        Tank::findOrFail($id)->update($request->validate([
            'tank_name' => 'required|string|max:100',
            'tank_shape' => 'required|in:kotak,bulat',
            'height' => 'required|numeric|min:1',
        ]));
        return back()->with('success', 'Tangki diperbarui.');
    }

    public function destroyTank(int $id)
    {
        $this->check('delete');
        $tank = Tank::findOrFail($id);
        if ($tank->devices()->exists()) {
            return back()->with('error', 'Tangki masih dipakai oleh perangkat.');
        }
        $tank->delete();
        return back()->with('success', 'Tangki dihapus.');
    }

    // ---------- POMPA ----------
    public function pumps()
    {
        $this->check();
        return view('settings.pumps', ['pumps' => Pump::all()]);
    }

    private function pumpRules(): array
    {
        return [
            'pump_name' => 'required|string|max:100',
            'flow_rate_lps' => 'nullable|numeric|min:0',
            'power_hp' => 'nullable|numeric|min:0',
            'power_watt' => 'nullable|integer|min:0',
            'delay_seconds' => 'nullable|integer|min:0',
            'on_duration_seconds' => 'required|integer|min:1',
            'off_duration_seconds' => 'required|integer|min:1',
        ];
    }

    public function storePump(Request $request)
    {
        $this->check('create');
        Pump::create($request->validate($this->pumpRules()));
        return back()->with('success', 'Pompa ditambahkan.');
    }

    public function updatePump(Request $request, int $id)
    {
        $this->check('update');
        Pump::findOrFail($id)->update($request->validate($this->pumpRules()));
        return back()->with('success', 'Pompa diperbarui.');
    }

    public function destroyPump(int $id)
    {
        $this->check('delete');
        $pump = Pump::findOrFail($id);
        if (\App\Models\Device::where('pump_id', $id)->exists()) {
            return back()->with('error', 'Pompa masih dipakai oleh perangkat.');
        }
        $pump->delete();
        return back()->with('success', 'Pompa dihapus.');
    }

    // ---------- SENSOR ----------
    public function sensors()
    {
        $this->check();
        return view('settings.sensors', ['sensors' => Sensor::all()]);
    }

    private function sensorRules(): array
    {
        return [
            'sensor_name' => 'required|string|max:100',
            'sensor_type' => 'required|string|max:50',
            'full_tank_distance' => 'required|integer|min:1',
            'trigger_percentage' => 'required|integer|min:1|max:100',
        ];
    }

    public function storeSensor(Request $request)
    {
        $this->check('create');
        Sensor::create($request->validate($this->sensorRules()));
        return back()->with('success', 'Sensor ditambahkan.');
    }

    public function updateSensor(Request $request, int $id)
    {
        $this->check('update');
        Sensor::findOrFail($id)->update($request->validate($this->sensorRules()));
        return back()->with('success', 'Sensor diperbarui.');
    }

    public function destroySensor(int $id)
    {
        $this->check('delete');
        $sensor = Sensor::findOrFail($id);
        if (\App\Models\Device::where('sensor_id', $id)->exists()) {
            return back()->with('error', 'Sensor masih dipakai oleh perangkat.');
        }
        $sensor->delete();
        return back()->with('success', 'Sensor dihapus.');
    }

    // ---------- TARIF ----------
    public function tariff()
    {
        $this->check();
        return view('settings.tariff', ['settings' => IndicatorSetting::getSettings()]);
    }

    public function updateTariff(Request $request)
    {
        $this->check('update');
        IndicatorSetting::query()->first()->update($request->validate([
            'water_price' => 'required|numeric|min:0',
            'admin_fee' => 'required|numeric|min:0',
        ]));
        return back()->with('success', 'Tarif diperbarui.');
    }

    // ---------- TAMPILAN / INDIKATOR ----------
    public function display()
    {
        $this->check();
        return view('settings.display', [
            'settings' => IndicatorSetting::getSettings(),
            'templates' => \App\Models\GaugeTemplate::all(),
        ]);
    }

    public function updateDisplay(Request $request)
    {
        $this->check('update');
        IndicatorSetting::query()->first()->update($request->validate([
            'threshold_low' => 'required|integer|min:0|max:100',
            'color_low' => 'required|string|size:7',
            'threshold_medium' => 'required|integer|min:0|max:100',
            'color_medium' => 'required|string|size:7',
            'color_high' => 'required|string|size:7',
            'active_template_id' => 'required|string|max:50',
        ]));
        return back()->with('success', 'Pengaturan tampilan disimpan.');
    }
}
