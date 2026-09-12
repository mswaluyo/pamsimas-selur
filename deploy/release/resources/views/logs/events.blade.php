@extends('layouts.app')
@section('title', 'Log Event')

@section('content')
<form method="GET" class="mb-4">
    <select name="type" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Semua tipe</option>
        @foreach($types as $type)
        <option value="{{ $type }}" @if(request('type') === $type)selected@endif>{{ $type }}</option>
        @endforeach
    </select>
</form>

<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3">Waktu</th>
                <th class="px-4 py-3">Perangkat</th>
                <th class="px-4 py-3">Tipe</th>
                <th class="px-4 py-3">Pesan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            <tr class="border-b hover:bg-slate-50">
                <td class="px-4 py-3 text-xs">{{ $log->event_time->format('d-m-Y H:i:s') }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $log->device?->mac_address ?? '-' }}</td>
                <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $log->event_type }}</span></td>
                <td class="px-4 py-3">{{ $log->message }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Belum ada event.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="border-t px-4 py-3">{{ $logs->links() }}</div>
</div>
@endsection