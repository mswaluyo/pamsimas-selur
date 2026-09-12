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
                    </tr>
                </thead>
                <tbody>
                    @forelse($sensors as $s)
                    <tr class="border-b">
                        <td class="px-4 py-3 font-medium">{{ $s->sensor_name }}</td>
                        <td class="px-4 py-3">{{ $s->sensor_type }}</td>
                        <td class="px-4 py-3">{{ $s->full_tank_distance }}</td>
                        <td class="px-4 py-3">{{ $s->trigger_percentage }}%</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Belum ada sensor.</td></tr>
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
@endsection
