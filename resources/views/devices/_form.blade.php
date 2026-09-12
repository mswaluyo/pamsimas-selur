@php($isEdit = isset($device))
<form method="POST" action="{{ $isEdit ? route('devices.update', $device->id) : route('devices.create') }}" class="space-y-4">
    @csrf
    @if(!$isEdit)
    <div>
        <label class="mb-1 block text-sm font-medium">MAC Address</label>
        <input type="text" name="mac_address" required placeholder="AA:BB:CC:DD:EE:FF" maxlength="17"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
        <p class="mt-1 text-xs text-slate-500">Terlihat di serial monitor / deteksi otomatis menu "Perangkat Terdeteksi".</p>
    </div>
    @endif
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-medium">Tipe Perangkat</label>
            <select name="device_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="MONITOR" @if($isEdit && $device->device_type === 'MONITOR')selected@endif>MONITOR (sensor saja)</option>
                <option value="ACTUATOR" @if($isEdit && $device->device_type === 'ACTUATOR')selected@endif>ACTUATOR (sensor + pompa)</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Mode Kontrol</label>
            <select name="control_mode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach(['AUTO', 'MANUAL', 'TIMED'] as $m)
                <option value="{{ $m }}" @if($isEdit && $device->control_mode === $m)selected@endif>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Tangki</label>
            <select name="tank_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach($tanks as $t)
                <option value="{{ $t->id }}" @if($isEdit && $device->tank_id == $t->id)selected@endif>{{ $t->tank_name }} (tinggi {{ $t->height }} cm)</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Pompa</label>
            <select name="pump_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach($pumps as $p)
                <option value="{{ $p->id }}" @if($isEdit && $device->pump_id == $p->id)selected@endif>{{ $p->pump_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Sensor</label>
            <select name="sensor_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">— Tanpa sensor master —</option>
                @foreach($sensors as $s)
                <option value="{{ $s->id }}" @if($isEdit && $device->sensor_id == $s->id)selected@endif>{{ $s->sensor_name }} ({{ $s->sensor_type }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Interval Lapor (detik)</label>
            <input type="number" name="report_interval" min="1" value="{{ $device->report_interval ?? 3 }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Jarak Penuh (cm)</label>
            <input type="number" name="full_tank_distance" min="1" value="{{ $device->full_tank_distance ?? 30 }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Jarak Kosong / Tinggi Tangki (cm)</label>
            <input type="number" name="empty_tank_distance" min="1" value="{{ $device->empty_tank_distance ?? 200 }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Trigger Pompa (%)</label>
            <input type="number" name="trigger_percentage" min="1" max="100" value="{{ $device->trigger_percentage ?? 70 }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
    </div>
    <button class="rounded-lg bg-sky-600 px-5 py-2.5 font-semibold text-white hover:bg-sky-700">
        {{ $isEdit ? 'Simpan Perubahan' : 'Daftarkan Perangkat' }}
    </button>
</form>
