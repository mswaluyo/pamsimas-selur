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
                        <th class="px-4 py-3">Tipe</th>
                        <th class="px-4 py-3">Jarak Penuh (cm)</th>
                        <th class="px-4 py-3">Trigger (%)</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sensors as $s)
                    <tr class="border-b">
                        <td class="px-4 py-3 font-medium">{{ $s->sensor_name }}</td>
                        <td class="px-4 py-3">{{ $s->sensor_type }}</td>
                        <td class="px-4 py-3">{{ $s->full_tank_distance }}</td>
                        <td class="px-4 py-3">{{ $s->trigger_percentage }}%</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-1">
                                <button type="button" onclick="editSensor('{{ $s->id }}', '{{ addslashes($s->sensor_name) }}', '{{ $s->sensor_type }}', '{{ $s->full_tank_distance }}', '{{ $s->trigger_percentage }}')"
                                        class="rounded bg-amber-100 px-2 py-1 text-xs text-amber-700 hover:bg-amber-200">Edit</button>
                                <form method="POST" action="{{ route('settings.sensors') }}/{{ $s->id }}/delete" onsubmit="return confirm('Hapus sensor ini?')">
                                    @csrf
                                    <button class="rounded bg-red-100 px-2 py-1 text-xs text-red-700 hover:bg-red-200">Hapus</button>
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
            <input type="text" name="sensor_name" required placeholder="Nama sensor" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="text" name="sensor_type" value="JSN-SR04T" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="number" name="full_tank_distance" required min="1" placeholder="Jarak penuh (cm)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="number" name="trigger_percentage" required min="1" max="100" placeholder="Trigger (%)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="w-full rounded-lg bg-sky-600 py-2 font-semibold text-white hover:bg-sky-700">Simpan</button>
        </form>
    </div>
</div>

{{-- Modal Edit Sensor --}}
<div id="sensorModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 font-semibold">Edit Sensor</h2>
        <form id="sensorEditForm" method="POST" class="space-y-3">
            @csrf
            <input type="text" id="sensor_name" name="sensor_name" required placeholder="Nama sensor" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="text" id="sensor_type" name="sensor_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="number" id="full_tank_distance" name="full_tank_distance" required min="1" placeholder="Jarak penuh (cm)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="number" id="trigger_percentage" name="trigger_percentage" required min="1" max="100" placeholder="Trigger (%)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
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
