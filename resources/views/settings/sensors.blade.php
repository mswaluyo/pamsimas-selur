@extends('layouts.app')
@section('title', 'Pengaturan Sensor')

@section('content')
<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="overflow-x-auto rounded-xl bg-white shadow">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-xs uppercase text-slate-500">
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3 hide-mobile">Tipe</th>
                        <th class="px-4 py-3">Jarak Penuh (cm)</th>
                        <th class="px-4 py-3">Trigger (%)</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sensors as $s)
                    <tr class="border-b">
                        <td class="px-4 py-3 font-medium">{{ $s->sensor_name }}</td>
                        <td class="px-4 py-3 hide-mobile">{{ $s->sensor_type }}</td>
                        <td class="px-4 py-3">{{ $s->full_tank_distance }}</td>
                        <td class="px-4 py-3">{{ $s->trigger_percentage }}%</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-1">
                                <button type="button" onclick="editSensor('{{ $s->id }}', '{{ addslashes($s->sensor_name) }}', '{{ $s->sensor_type }}', '{{ $s->full_tank_distance }}', '{{ $s->trigger_percentage }}')"
                                        class="rounded bg-amber-100 px-2 py-1 text-xs text-amber-700 hover:bg-amber-200">Edit</button>
                                <form method="POST" action="{{ route('settings.sensors') }}/{{ $s->id }}/delete" onsubmit="return confirm('Hapus sensor ini?')">
                                    @csrf
                                    <button class="rounded bg-red-100 px-2 py-1 text-xs text-red-700 hover:bg-red-200"><i class="fas fa-trash-can mr-1"></i>Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Belum ada sensor.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 font-semibold">Tambah Sensor</h2>
        <form method="POST" action="{{ route('settings.sensors') }}" class="space-y-3">
            @csrf
            <div>
                <label for="add-sensor-name" class="mb-1 block text-sm font-medium">Nama Sensor</label>
                <input type="text" id="add-sensor-name" name="sensor_name" required placeholder="cth: Sensor Bak Atas" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="add-sensor-type" class="mb-1 block text-sm font-medium">Tipe Sensor</label>
                <input type="text" id="add-sensor-type" name="sensor_type" value="JSN-SR04T" placeholder="cth: HC-SR04 / JSN-SR04T" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="add-full-tank-distance" class="mb-1 block text-sm font-medium">Jarak Sensor saat Penuh (cm)</label>
                <input type="number" id="add-full-tank-distance" name="full_tank_distance" required min="1" placeholder="cth: 30" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Jarak yang diukur sensor ketika permukaan air di titik penuh.</p>
            </div>
            <div>
                <label for="add-trigger-percentage" class="mb-1 block text-sm font-medium">Ambang Trigger Pompa (%)</label>
                <input type="number" id="add-trigger-percentage" name="trigger_percentage" required min="1" max="100" placeholder="cth: 70" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Level (%) saat pompa mulai dinyalakan kembali.</p>
            </div>
            <button class="w-full rounded-lg bg-sky-600 py-2 font-semibold text-white hover:bg-sky-700">Simpan</button>
        </form>
    </div>
</div>

{{-- Modal Edit Sensor --}}
<div id="sensorModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 font-semibold">Edit Sensor</h2>
        <form id="sensorEditForm" method="POST" class="space-y-3">
            @csrf
            <div>
                <label for="sensor_name" class="mb-1 block text-sm font-medium">Nama Sensor</label>
                <input type="text" id="sensor_name" name="sensor_name" required placeholder="cth: Sensor Bak Atas" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="sensor_type" class="mb-1 block text-sm font-medium">Tipe Sensor</label>
                <input type="text" id="sensor_type" name="sensor_type" placeholder="cth: HC-SR04 / JSN-SR04T" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="full_tank_distance" class="mb-1 block text-sm font-medium">Jarak Sensor saat Penuh (cm)</label>
                <input type="number" id="full_tank_distance" name="full_tank_distance" required min="1" placeholder="cth: 30" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Jarak yang diukur sensor ketika permukaan air di titik penuh (dibawa ke perangkat sebagai <code>full_tank_distance</code>).</p>
            </div>
            <div>
                <label for="trigger_percentage" class="mb-1 block text-sm font-medium">Ambang Trigger Pompa (%)</label>
                <input type="number" id="trigger_percentage" name="trigger_percentage" required min="1" max="100" placeholder="cth: 70" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Level (%) saat pompa mulai dinyalakan kembali.</p>
            </div>
            <div class="flex gap-2">
                <button class="w-full rounded-lg bg-sky-600 py-2 font-semibold text-white hover:bg-sky-700">Simpan Perubahan</button>
                <button type="button" onclick="closeModal('sensorModal')" class="w-full rounded-lg bg-slate-200 py-2 font-semibold text-slate-700 hover:bg-slate-300">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function editSensor(id, name, type, full, trigger) {
    const f = document.getElementById('sensorEditForm');
    f.action = '{{ route('settings.sensors') }}/' + id;
    document.getElementById('sensor_name').value = name;
    document.getElementById('sensor_type').value = type;
    document.getElementById('full_tank_distance').value = full;
    document.getElementById('trigger_percentage').value = trigger;
    openModal('sensorModal');
}
function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('flex'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }
</script>
@endsection
