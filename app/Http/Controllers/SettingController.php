<?php

namespace App\Http\Controllers;

use App\Models\Device;
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
        $this->propagateMasterToDevices('tank_id', $id, "Tangki {$tank->tank_name} (tinggi {$tank->height} cm)");
        return back()->with('success', 'Tangki diperbarui & dikirim ke perangkat terkait.');
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
        $pump = Pump::findOrFail($id);
        $pump->update($request->validate($this->pumpRules()));
        $this->propagateMasterToDevices('pump_id', $id, "Pompa {$pump->pump_name} (ON {$pump->on_duration_seconds}s / OFF {$pump->off_duration_seconds}s)");
        return back()->with('success', 'Pompa diperbarui & dikirim ke perangkat terkait.');
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
        $sensor = Sensor::findOrFail($id);
        $sensor->update($request->validate($this->sensorRules()));
        $this->propagateMasterToDevices('sensor_id', $id, "Sensor {$sensor->sensor_name} (penuh {$sensor->full_tank_distance} cm / pemicu {$sensor->trigger_percentage}%)");
        return back()->with('success', 'Sensor diperbarui & dikirim ke perangkat terkait.');
    }

    /**
     * Setelah master data diubah, kirim ulang parameternya ke perangkat yang memakainya:
     * nilai turunan (jarak penuh/kosong, pemicu, durasi ON/OFF) disinkronkan lalu
     * config_update_command dinaikkan agar firmware menarik konfigurasi pada poll
     * berikutnya (flag baru turun setelah perangkat meng-ack lewat /api/update).
     */
    private function propagateMasterToDevices(string $column, int $masterId, string $label): void
    {
        $devices = Device::with(['tank', 'pump', 'sensor'])->where($column, $masterId)->get();
        if ($devices->isEmpty()) {
            return;
        }

        $touched = [];
        foreach ($devices as $device) {
            $device->syncFromMasterData();
            $device->config_update_command = true;
            $device->save();
            $touched[] = $device->mac_address;
        }

        \App\Models\AdminLog::create([
            'user_id' => session('user.id', 0),
            'action' => 'Sync Perangkat',
            'details' => $label . ' → konfigurasi dikirim ke: ' . implode(', ', $touched),
        ]);
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
        $role = session('user.role', 'Viewer');
        $templates = \App\Models\GaugeTemplate::all()->map(function ($t) {
            // Pratinjau tiap template dibangun sebagai dokumen mandiri (iframe srcdoc) supaya
            // CSS antar template tidak saling menimpa, sama seperti cara halaman gauge memakainya.
            $t->preview_srcdoc = $this->previewDoc($t);
            return $t;
        });
        return view('settings.display', [
            'settings' => IndicatorSetting::getSettings(),
            'templates' => $templates,
            // Template aktif dipilih lewat tombol "Aktifkan" di halaman ini, jadi hak aksesnya
            // mengikuti modul 'templates' agar Operator/Administrator tetap sama seperti sebelumnya.
            'activeId' => IndicatorSetting::getSettings()['active_template_id'] ?? 'tank_gauge',
            'canTemplates' => \App\Support\Permission::can($role, 'templates', 'read'),
            'canTemplatesEdit' => \App\Support\Permission::can($role, 'templates', 'update'),
            'previewPercent' => 65,
        ]);
    }

    /** Dokumen HTML mandiri untuk pratinjau gauge (dipakai di iframe srcdoc). */
    private function previewDoc($t): string
    {
        $html = (string) $t->html_code;
        foreach ([['TANK_NAME', 'Bak Contoh'], ['PUMP_NAME', 'Pompa Contoh']] as [$k, $v]) {
            $html = preg_replace('/{{\s*' . $k . '\s*}}/i', $v, $html);
        }
        $html = preg_replace('/{{\s*DEVICE[ _-]*ID\s*}}/i', '0', $html);
        $html = trim($html) !== ''
            ? $html
            : '<p style="color:#94a3b8;font-size:11px;text-align:center">Template ini belum memiliki kode HTML.</p>';

        $pct = 65;
        $css = (string) $t->css_code;
        $js = (string) $t->js_code;

        // "#pv" = wrapper pemusat, "#pvw" = isi template yang diskalakan agar pas di iframe.
        // Skala TIDAK lagi hard-code (dulu scale(.45) menyebabkan sisa ruang kosong / meluber
        // saat kartu jadi 4 kolom); dihitung ulang oleh fit() dari lebar x tinggi sebenarnya.
        return '<!doctype html><html lang="id"><head><meta charset="utf-8">'
            . '<style>*{box-sizing:border-box}html,body{margin:0;height:100%;overflow:hidden;background:#fff;'
            . "font-family:system-ui,-apple-system,'Segoe UI',sans-serif}"
            . '#pv{display:flex;align-items:center;justify-content:center;height:100%;width:100%;overflow:hidden;padding:2px}'
            . '#pvw{display:inline-block;transform-origin:center center;max-width:100%}'
            . '#pvw>*{margin-left:auto;margin-right:auto}'
            . '#pvw .gauge-title{font-size:11px;color:#64748b;text-align:center}'
            . $css . '</style></head><body><div id="pv"><div id="pvw">' . $html . '</div></div><script>'
            . '(function(){var card=document.getElementById("pv"),inner=document.getElementById("pvw");'
            // Skala agar isi template pas penuh ke dalam iframe (tidak ada sisa ruang, tidak meluber).
            // Batas atas 1.8: pratinjau boleh diperbesar supaya kontainer terisi (mis. simple_bar
            // yang aslinya kecil), tapi tidak dibesarkan berlebihan sampai blur.
            . 'function fit(){try{var cw=card.clientWidth-4,ch=card.clientHeight-4;'
            . 'var w=inner.offsetWidth,h=inner.offsetHeight;if(!cw||!ch||!w||!h)return;'
            . 'var s=Math.min(cw/w,ch/h,1.8);inner.style.transform="scale("+s+")";}catch(e){}}'
            // Fallback universal: salinan persis universalUpdateGauge() di devices/show.blade.php
            . 'function universalUpdateGauge(el0,v,fill){el0.querySelectorAll("[data-update-style]").forEach(function(el){'
            . 'var p=el.dataset.updateStyle;'
            . 'if(p==="degrees"){el.style.setProperty("--percentage",(v*2.7)+"deg");el.style.setProperty("--fill-color",fill);}'
            . 'else if(p==="percentage"){if(el.classList.contains("tank-gauge-water")){el.style.height=v+"%";}else{el.style.width=v+"%";}'
            . 'el.style.backgroundColor=fill;}});'
            . 'var t=el0.querySelector(".value")||el0.querySelector(".tank-gauge-text")'
            . '||el0.querySelector(".simple-bar-gauge-text");'
            // Hindari "65%%": bila ada "%" tepat setelah elemen nilai (teks atau <small>%</small>), tulis angkanya saja
            . 'if(t){var n=Math.round(v);var nx=t.nextSibling;var xt=nx?String(nx.textContent||"").trim():"";'
            . 't.textContent=(xt==="%"||xt.indexOf("%")!==-1)?String(n):n+"%";}}'
            . 'try{' . $js . '}catch(e){}'
            . 'try{if(typeof window.initGauge==="function"){window.initGauge(card);}}catch(e){}'
            . 'try{if(typeof window.updateGauge==="function"){window.updateGauge(card,' . $pct . ',"#22c55e");}'
            . 'else{universalUpdateGauge(card,' . $pct . ',"#22c55e");}}catch(e){universalUpdateGauge(card,' . $pct . ',"#22c55e");}'
            . 'universalUpdateGauge(card,' . $pct . ',"#22c55e");'
            // Terapkan skala setelah gambar & update pertama selesai (juga saat ukuran iframe berubah)
            . 'if(window.ResizeObserver){new ResizeObserver(fit).observe(card);}else{window.addEventListener("resize",fit);}'
            . 'fit();requestAnimationFrame(fit);setTimeout(fit,60);'
            . '})();</script></body></html>';
    }

    public function updateDisplay(Request $request)
    {
        $this->check('update');
        $data = $request->validate([
            'threshold_low' => 'required|integer|min:0|max:100',
            'color_low' => 'required|string|size:7',
            'threshold_medium' => 'required|integer|min:0|max:100',
            'color_medium' => 'required|string|size:7',
            'color_high' => 'required|string|size:7',
            // Template aktif sekarang dipilih lewat tombol "Aktifkan" di halaman yang sama,
            // jadi field ini opsional & tidak boleh menimpa nilai lama bila tidak dikirim.
            'active_template_id' => 'nullable|string|max:50',
        ]);
        if (empty($data['active_template_id'])) {
            unset($data['active_template_id']);
        }
        IndicatorSetting::query()->first()->update($data);
        return back()->with('success', 'Pengaturan tampilan disimpan.');
    }
}
