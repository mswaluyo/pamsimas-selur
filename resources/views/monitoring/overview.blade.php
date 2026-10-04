@extends('layouts.app')
@section('title', 'Monitoring Sistem')

@section('content')
<div class="mb-4 grid grid-cols-1 gap-5 lg:grid-cols-2">
    <a href="{{ route('monitoring.system') }}" class="rounded-xl bg-white p-6 shadow transition hover:shadow-lg">
        <h2 class="text-lg font-bold"><i class="fas fa-server"></i> Sistem</h2>
        <p class="mt-1 text-sm text-slate-500">Info PHP, Laravel, memori, disk, dan timezone.</p>
    </a>
    <a href="{{ route('monitoring.database') }}" class="rounded-xl bg-white p-6 shadow transition hover:shadow-lg">
        <h2 class="text-lg font-bold"><i class="fas fa-database"></i> Database</h2>
        <p class="mt-1 text-sm text-slate-500">Ukuran tabel & jumlah baris pada database terkait.</p>
    </a>
    <a href="{{ route('monitoring.performance') }}" class="rounded-xl bg-white p-6 shadow transition hover:shadow-lg">
        <h2 class="text-lg font-bold"><i class="fas fa-tachometer-alt"></i> Performa</h2>
        <p class="mt-1 text-sm text-slate-500">Latensi query & aktivitas log 24 jam terakhir.</p>
    </a>
</div>

<h2 class="mb-3 font-semibold">Perangkat IoT</h2>
<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse($devices as $d)
    <div class="rounded-xl bg-white p-5 shadow">
        <div class="flex items-center justify-between">
            <span class="flex items-center gap-2">
                <span class="font-mono text-xs font-semibold">{{ $d->mac_address }}</span>
                {{-- Tipe perangkat: badge MON / ACT — gaya sama dengan kartu gauge (TODO §7.19) --}}
                @php($tipe = strtoupper((string) $d->device_type))
                @if(in_array($tipe, ['MONITOR', 'ACTUATOR']))
                <span class="device-type-badge {{ $tipe === 'MONITOR' ? 'is-mon' : 'is-act' }}"
                      title="Tipe perangkat: {{ $d->device_type }}">{{ $tipe === 'MONITOR' ? 'MON' : 'ACT' }}</span>
                @endif
            </span>
            <span class="flex items-center gap-1.5 text-xs">
                <span class="h-2 w-2 rounded-full {{ $d->isOnline() ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                {{ $d->isOnline() ? 'Online' : 'Offline' }}
            </span>
        </div>
        <p class="mt-2 text-sm text-slate-600">Tangki: <strong>{{ $d->tank?->tank_name ?? '-' }}</strong></p>
        <p class="text-sm text-slate-600">Status: <strong>{{ $d->status }}</strong> · Mode: {{ $d->control_mode }}</p>
        <p class="mt-1 text-xs text-slate-400">Update: {{ $d->last_update?->format('d-m-Y H:i:s') ?? '-' }}</p>
    </div>
    @empty
    <p class="text-slate-400">Belum ada perangkat.</p>
    @endforelse
</div>
@endsection

@push('styles')
<style>
    /* Badge tipe perangkat (MON / ACT) — aturan identik dengan dashboard & halaman detail (§7.19) */
    .device-type-badge { font-size:.62rem; font-weight:700; letter-spacing:.04em; border-radius:4px; padding:1px 5px; border:1px solid transparent; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; flex:none; }
    .device-type-badge.is-mon { background:#eef2ff; color:#4338ca; border-color:#c7d2fe; }
    .device-type-badge.is-act { background:#fff7ed; color:#c2410c; border-color:#fed7aa; }
</style>
@endpush