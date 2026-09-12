@extends('layouts.app')
@section('title', 'Riwayat Log Pompa')

@section('content')
<form method="GET" class="mb-4">
    <select name="device_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Semua perangkat</option>
        @foreach($devices as $d)
        <option value="{{ $d->id }}" @if(request('device_id') == $d->id)selected@endif>{{ $d->mac_address }}</option>
        @endforeach
    </select>
</form>

<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3">Waktu</th>
                <th class="px-4 py-3">Perangkat</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Mode</th>
                <th class="px-4 py-3">Durasi (dtk)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            <tr class="border-b hover:bg-slate-50">
                <td class="px-4 py-3 text-xs">{{ $log->timestamp->format('d-m-Y H:i:s') }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $log->device?->mac_address ?? '-' }}</td>
                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $log->pump_status === 'ON' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $log->pump_status }}</span></td>
                <td class="px-4 py-3 text-xs">{{ $log->control_mode }}</td>
                <td class="px-4 py-3">{{ $log->duration_seconds }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Belum ada log.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="border-t px-4 py-3">{{ $logs->links() }}</div>
</div>
@endsection