@extends('layouts.app')
@section('title', 'Audit Trail Admin')

@section('content')
<form method="GET" class="mb-4">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari aksi / detail..." class="w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm">
    <button class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white">Cari</button>
</form>

<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3">Waktu</th>
                <th class="px-4 py-3">Aksi</th>
                <th class="px-4 py-3">Detail</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            <tr class="border-b hover:bg-slate-50">
                <td class="px-4 py-3 text-xs">{{ $log->created_at?->format('d-m-Y H:i') ?? '-' }}</td>
                <td class="px-4 py-3"><span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs text-sky-700">{{ $log->action }}</span></td>
                <td class="px-4 py-3">{{ $log->details }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="px-4 py-8 text-center text-slate-400">Belum ada log admin.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="border-t px-4 py-3">{{ $logs->links() }}</div>
</div>
@endsection