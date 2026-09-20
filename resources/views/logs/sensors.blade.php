@extends('layouts.app')
@section('title', 'Log Sensor')

@section('content')
<form method="GET" class="mb-4">
    <select name="device_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Semua perangkat</option>
        @foreach($devices as $d)
        <option value="{{ $d->id }}" @if(request('device_id') == $d->id) selected @endif>{{ $d->mac_address }}</option>
        @endforeach
    </select>
</form>

<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3">Waktu</th>
                <th class="px-4 py-3">Perangkat</th>
                <th class="px-4 py-3">Level (cm)</th>
                <th class="px-4 py-3">Persen</th>
                <th class="px-4 py-3">RSSI</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            <tr class="border-b hover:bg-slate-50">
                <td class="px-4 py-3 text-xs">{{ $log->record_time->format('d-m-Y H:i:s') }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $log->device?->mac_address ?? '-' }}</td>
                <td class="px-4 py-3">{{ $log->water_level }}</td>
                <td class="px-4 py-3 font-semibold">{{ number_format($log->water_percentage, 1) }}%</td>
                <td class="px-4 py-3">{{ $log->rssi }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Belum ada log.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="border-t px-4 py-3">{{ $logs->links() }}</div>
</div>
@endsection