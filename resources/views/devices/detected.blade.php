@extends('layouts.app')
@section('title', 'Perangkat Terdeteksi')

@section('content')
<div class="mb-4">
    <h2 class="font-semibold">Perangkat Terdeteksi Otomatis</h2>
    <p class="text-sm text-slate-500">MAC address yang mencoba mengirim data dengan API key valid tetapi belum terdaftar.</p>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3">MAC Address</th>
                <th class="px-4 py-3">Pertama Terlihat</th>
                <th class="px-4 py-3">Terakhir Terlihat</th>
                <th class="px-4 py-3">Jumlah Akses</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detected as $d)
            <tr class="border-b hover:bg-slate-50">
                <td class="px-4 py-3 font-mono text-xs">{{ $d->mac_address }}</td>
                <td class="px-4 py-3 text-xs">{{ $d->first_seen?->format('d-m-Y H:i') ?? '-' }}</td>
                <td class="px-4 py-3 text-xs">{{ $d->last_seen?->format('d-m-Y H:i') ?? '-' }}</td>
                <td class="px-4 py-3">{{ $d->hits }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Tidak ada perangkat tak dikenal.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
