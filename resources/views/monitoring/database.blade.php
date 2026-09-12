@extends('layouts.app')
@section('title', 'Monitoring Database')

@section('content')
<div class="mb-4 rounded-lg bg-slate-100 px-4 py-3 text-sm">Total ukuran database: <strong>{{ $dbSizeMb }} MB</strong></div>
<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3">Tabel</th>
                <th class="px-4 py-3">Baris</th>
                <th class="px-4 py-3">Ukuran (MB)</th>
                <th class="px-4 py-3">Engine</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tables as $t)
            <tr class="border-b hover:bg-slate-50">
                <td class="px-4 py-3 font-medium">{{ $t['name'] }}</td>
                <td class="px-4 py-3">{{ number_format($t['rows']) }}</td>
                <td class="px-4 py-3">{{ $t['size_mb'] }}</td>
                <td class="px-4 py-3">{{ $t['engine'] }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection