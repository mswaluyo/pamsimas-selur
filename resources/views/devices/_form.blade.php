@php($isEdit = isset($device))
<form method="POST" action="{{ $isEdit ? route('devices.update', $device->id) : route('devices.create') }}" class="space-y-4">
    @csrf
    @if(!$isEdit)
    <div>
        <label class="mb-1 block text-sm font-medium">MAC Address</label>
        <input type="text" name="mac_address" required placeholder="AA:BB:CC:DD:EE:FF" maxlength="17"
               value="{{ old('mac_address', $prefillMac ?? '') }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
        <p class="mt-1 text-xs text-slate-500">Terlihat di serial monitor / deteksi otomatis menu "Perangkat Terdeteksi".</p>
    </div>
    @endif
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-medium">Tipe Perangkat</label>
            <select name="device_type" id="sel-device-type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="MONITOR" @if($isEdit && $device->device_type === 'MONITOR') selected @endif>MONITOR - sensor + pompa (fungsi ganda)</option>
                <option value="ACTUATOR" @if($isEdit && $device->device_type === 'ACTUATOR') selected @endif>ACTUATOR - pompa saja (tanpa baca sensor)</option>
            </select>
            <div class="mt-1.5 space-y-1 text-xs leading-relaxed text-slate-500">
                <p id="hint-type-monitor" class="hidden">
                    <span class="font-semibold text-slate-700">MONITOR = fungsi ganda (sensor + pompa)</span>: papan ini membaca sensor
                    ultrasonik lalu melaporkannya ke server setiap &ldquo;Interval Lapor&rdquo;,
                    <span class="text-amber-700">sekaligus menggerakkan relay pompa sendiri</span> karena pada mode AUTO relay papan ini ikut
                    logika level air. Level air dari papan ini juga yang dipakai mengontrol perangkat ACTUATOR di tangki yang sama, jadi
                    <span class="text-amber-700">satu papan MONITOR saja sudah cukup</span> untuk sensor sekaligus pompa.
                </p>
                <p id="hint-type-actuator" class="hidden">
                    <span class="font-semibold text-slate-700">ACTUATOR = pompa saja</span>: papan hanya mengeksekusi nyala/mati pompa (plus
                    timer ON/OFF saat link terputus). <span class="text-amber-700">Pembacaan sensor fisik di papan ini dilewati</span> &mdash;
                    papan ini tidak punya andil sensor &mdash; level air diambil dari perangkat MONITOR di tangki yang sama. Pilih ini bila papan
                    memang hanya penggerak pompa dan MONITOR sudah ada di tangki tersebut; kalau tidak ada MONITOR, level air tidak pernah
                    diperbarui dan pompa tidak bekerja sesuai pemicu.
                </p>
            </div>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Mode Kontrol</label>
            <select name="control_mode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach(['AUTO', 'MANUAL', 'TIMED'] as $m)
                <option value="{{ $m }}" @if($isEdit && $device->control_mode === $m) selected @endif>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Tangki</label>
            <select name="tank_id" id="sel-tank" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @if(!$isEdit) onchange="autoFill()" @endif>
                @foreach($tanks as $t)
                <option value="{{ $t->id }}" data-height="{{ $t->height }}" @if($isEdit && $device->tank_id == $t->id) selected @endif>{{ $t->tank_name }} (tinggi {{ $t->height }} cm)</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Pompa</label>
            <select name="pump_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach($pumps as $p)
                <option value="{{ $p->id }}" data-on="{{ $p->on_duration_seconds }}" data-off="{{ $p->off_duration_seconds }}" @if($isEdit && $device->pump_id == $p->id) selected @endif>{{ $p->pump_name }} (ON {{ $p->on_duration_seconds }}s / OFF {{ $p->off_duration_seconds }}s)</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Sensor</label>
            <select name="sensor_id" id="sel-sensor" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @if(!$isEdit) onchange="autoFill()" @endif>
                <option value="" data-full="" data-trigger="">— Tanpa sensor master —</option>
                @foreach($sensors as $s)
                <option value="{{ $s->id }}" data-full="{{ $s->full_tank_distance }}" data-trigger="{{ $s->trigger_percentage }}" @if($isEdit && $device->sensor_id == $s->id) selected @endif>{{ $s->sensor_name }} ({{ $s->sensor_type }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Interval Lapor (detik)</label>
            <input type="number" name="report_interval" min="1" value="{{ $device->report_interval ?? 3 }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Jarak Penuh (cm) <span class="text-xs text-emerald-600">otomatis dari Sensor</span></label>
            <input type="number" name="full_tank_distance" min="1" value="{{ $device->full_tank_distance ?? 30 }}" id="f-full"
                   readonly class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Jarak Kosong / Tinggi Tangki (cm) <span class="text-xs text-emerald-600">otomatis dari Tangki</span></label>
            <input type="number" name="empty_tank_distance" min="1" value="{{ $device->empty_tank_distance ?? 200 }}" id="f-empty"
                   readonly class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Trigger Pompa (%) <span class="text-xs text-emerald-600">otomatis dari Sensor</span></label>
            <input type="number" name="trigger_percentage" min="1" max="100" value="{{ $device->trigger_percentage ?? 70 }}" id="f-trigger"
                   readonly class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
        </div>
    </div>
    <p class="text-xs text-slate-500">Durasi pompa (ON/OFF) untuk mode TIMED juga otomatis mengikuti master data pompa yang dipilih.</p>
    <button class="rounded-lg bg-sky-600 px-5 py-2.5 font-semibold text-white hover:bg-sky-700">
        {{ $isEdit ? 'Simpan Perubahan' : 'Daftarkan Perangkat' }}
    </button>
</form>

<script>
// Tampilkan penjelasan tipe perangkat yang dipilih (MONITOR vs ACTUATOR).
(function () {
    const typeSel = document.getElementById('sel-device-type');
    if (!typeSel) return;
    const syncTypeHint = () => {
        document.getElementById('hint-type-monitor').classList.toggle('hidden', typeSel.value !== 'MONITOR');
        document.getElementById('hint-type-actuator').classList.toggle('hidden', typeSel.value !== 'ACTUATOR');
    };
    typeSel.addEventListener('change', syncTypeHint);
    syncTypeHint();
})();
</script>

@if(!$isEdit)
<script>
function autoFill() {
    const tankSel = document.getElementById('sel-tank');
    const tankOpt = tankSel.options[tankSel.selectedIndex];
    if (tankOpt && tankOpt.dataset.height) {
        document.getElementById('f-empty').value = tankOpt.dataset.height;
    }
    const sensorSel = document.getElementById('sel-sensor');
    const sOpt = sensorSel.options[sensorSel.selectedIndex];
    if (sOpt && sOpt.dataset.full) {
        document.getElementById('f-full').value = sOpt.dataset.full;
        document.getElementById('f-trigger').value = sOpt.dataset.trigger;
    } else if (sOpt && sOpt.value === '') {
        document.getElementById('f-full').value = 30;
        document.getElementById('f-trigger').value = 70;
    }
}
autoFill();
</script>
@endif
