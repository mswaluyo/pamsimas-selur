@extends('layouts.app')
@section('title', 'Perangkat IoT')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="font-semibold">Daftar Perangkat Terdaftar</h2>
    <a href="{{ route('devices.create') }}" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">+ Daftarkan Perangkat</a>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3">MAC Address</th>
                <th class="px-4 py-3">Tipe</th>
                <th class="px-4 py-3">Tangki</th>
                <th class="px-4 py-3">Pompa</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Mode</th>
                <th class="px-4 py-3">Terakhir Update</th>
                <th class="px-4 py-3">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($devices as $d)
            <tr class="border-b hover:bg-slate-50">
                <td class="px-4 py-3 font-mono text-xs">{{ $d->mac_address }}</td>
                <td class="px-4 py-3">{{ $d->device_type }}</td>
                <td class="px-4 py-3">{{ $d->tank?->tank_name ?? '-' }}</td>
                <td class="px-4 py-3">{{ $d->pump?->pump_name ?? '-' }}</td>
                <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $d->isOnline() ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                        {{ $d->isOnline() ? 'Online' : 'Offline' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-xs">{{ $d->control_mode }}</td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ $d->last_update?->format('d-m H:i') ?? '-' }}</td>
                <td class="px-4 py-3">
                    <div class="flex gap-1">
                        <a href="{{ route('devices.show', $d->id) }}" class="rounded bg-slate-100 px-2 py-1 text-xs hover:bg-slate-200">Detail</a>
                        <a href="{{ route('devices.edit', $d->id) }}" class="rounded bg-sky-100 px-2 py-1 text-xs text-sky-700 hover:bg-sky-200">Edit</a>
                        <form method="POST" action="{{ route('devices.sync', $d->id) }}" onsubmit="return confirm('Sinkronkan dengan data master?')">
                            @csrf
                            <button class="rounded bg-violet-100 px-2 py-1 text-xs text-violet-700 hover:bg-violet-200">Sync</button>
                        </form>
                        <form method="POST" action="{{ route('devices.destroy', $d->id) }}" onsubmit="return confirm('Hapus perangkat ini?')">
                            @csrf
                            <button class="rounded bg-red-100 px-2 py-1 text-xs text-red-700 hover:bg-red-200">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">Belum ada perangkat terdaftar.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
