<?php

namespace App\Http\Controllers;

use App\Models\IndicatorSetting;
use App\Models\Pump;
use App\Models\Sensor;
use App\Models\Tank;
use App\Support\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $tanks = Tank::withCount('devices')->get();
        foreach ($tanks as $t) {
            if ($t->tank_shape === 'kotak' && $t->rectangular_dim_id) {
                $dim = DB::table('tank_rectangular_dimensions')->find($t->rectangular_dim_id);
                $t->rect_length = $dim->length ?? null;
                $t->rect_width = $dim->width ?? null;
            } elseif ($t->tank_shape === 'bulat' && $t->circular_dim_id) {
                $dim = DB::table('tank_circular_dimensions')->find($t->circular_dim_id);
                $t->circ_diameter = $dim->diameter ?? null;
            }
        }
        return view('settings.tanks', ['tanks' => $tanks]);
    }

    /**
     * Simpan/update dimensi tangki sesuai bentuk (P×L untuk kotak, ⌀ untuk bulat).
     */
    private function applyTankDimensions(Request $request, Tank $tank): void
    {
        if ($tank->tank_shape === 'kotak') {
            $len = (float) $request->input('rectangular_length', 0);
            $wid = (float) $request->input('rectangular_width', 0);
            if ($len > 0 && $wid > 0) {
                if ($tank->rectangular_dim_id) {
                    DB::table('tank_rectangular_dimensions')->where('id', $tank->rectangular_dim_id)
                        ->update(['length' => $len, 'width' => $wid]);
                } else {
                    $id = DB::table('tank_rectangular_dimensions')->insertGetId(['length' => $len, 'width' => $wid]);
                    $tank->update(['rectangular_dim_id' => $id, 'circular_dim_id' => null]);
                }
            }
        } elseif ($tank->tank_shape === 'bulat') {
            $d = (float) $request->input('circular_diameter', 0);
            if ($d > 0) {
                if ($tank->circular_dim_id) {
                    DB::table('tank_circular_dimensions')->where('id', $tank->circular_dim_id)
                        ->update(['diameter' => $d]);
                } else {
                    $id = DB::table('tank_circular_dimensions')->insertGetId(['diameter' => $d]);
                    $tank->update(['circular_dim_id' => $id, 'rectangular_dim_id' => null]);
                }
            }
        }
    }

    public function storeTank(Request $request)
    {
        $this->check('create');
        $tank = Tank::create($request->validate([
            'tank_name' => 'required|string|max:100',
            'tank_shape' => 'required|in:kotak,bulat',
            'height' => 'required|numeric|min:1',
        ]));
        $this->applyTankDimensions($request, $tank);
        return back()->with('success', 'Tangki ditambahkan.');
    }

    public function updateTank(Request $request, int $id)
    {
        $this->check('update');
        $tank = Tank::findOrFail($id);
        $tank->update($request->validate([
            'tank_name' => 'required|string|max:100',
            'tank_shape' => 'required|in:kotak,bulat',
            'height' => 'required|numeric|min:1',
        ]));
        $this->applyTankDimensions($request, $tank);
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
        return view('settings.tariff', [
            'settings' => IndicatorSetting::getSettings(),
            'history' => \App\Models\TariffHistory::orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function updateTariff(Request $request)
    {
        $this->check('update');
        $data = $request->validate([
            'water_price' => 'required|numeric|min:0',
            'admin_fee' => 'required|numeric|min:0',
        ]);

        $old = IndicatorSetting::getSettings();
        $oldPrice = (float) ($old['water_price'] ?? 0);
        $oldFee = (float) ($old['admin_fee'] ?? 0);
        $newPrice = (float) $data['water_price'];
        $newFee = (float) $data['admin_fee'];

        // Simpan tanpa perubahan nilai → tidak menulis histori (anti-duplikat)
        if (abs($oldPrice - $newPrice) < 0.005 && abs($oldFee - $newFee) < 0.005) {
            return back()->with('success', 'Tidak ada perubahan nilai tarif.');
        }

        IndicatorSetting::query()->first()->update($data);

        \App\Models\TariffHistory::create([
            'water_price' => $newPrice,
            'admin_fee' => $newFee,
            'old_water_price' => $oldPrice,
            'old_admin_fee' => $oldFee,
            'changed_by' => session('user.username', '-'),
            'created_at' => now(),
        ]);

        \App\Models\AdminLog::create([
            'user_id' => session('user.id', 0),
            'action' => 'Ubah Tarif',
            'details' => "Harga air {$oldPrice} → {$newPrice} Rp/m³, admin {$oldFee} → {$newFee} Rp",
        ]);

        return back()->with('success', 'Tarif diperbarui & tercatat di histori.');
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
