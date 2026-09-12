@extends('layouts.app')
@section('title', 'Pengaturan Tangki')

@section('content')
<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="overflow-x-auto rounded-xl bg-white shadow">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-xs uppercase text-slate-500">
                        <th class="px-4 py-3">Nama Tangki</th>
                        <th class="px-4 py-3">Bentuk</th>
                        <th class="px-4 py-3">Tinggi (cm)</th>
                        <th class="px-4 py-3">Dipakai Perangkat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tanks as $t)
                    <tr class="border-b">
                        <td class="px-4 py-3 font-medium">{{ $t->tank_name }}</td>
                        <td class="px-4 py-3">{{ ucfirst($t->tank_shape) }}</td>
                        <td class="px-4 py-3">{{ $t->height }}</td>
                        <td class="px-4 py-3">{{ $t->devices_count }} perangkat</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Belum ada tangki.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 font-semibold">Tambah Tangki</h2>
        <form method="POST" action="{{ route('settings.tanks') }}" class="space-y-3">
            @csrf
            <input type="text" name="tank_name" required placeholder="Nama tangki" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <select name="tank_shape" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="kotak">Kotak</option>
                <option value="bulat">Bulat</option>
            </select>
            <input type="number" name="height" required min="1" placeholder="Tinggi (cm)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="w-full rounded-lg bg-sky-600 py-2 font-semibold text-white hover:bg-sky-700">Simpan</button>
        </form>
    </div>
</div>
@endsection
