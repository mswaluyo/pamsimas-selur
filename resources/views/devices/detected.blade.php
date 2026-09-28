@extends('layouts.app')
@section('title', 'Perangkat Terdeteksi')

@section('content')
<div class="mb-4">
    <h2 class="font-semibold">Perangkat Terdeteksi Otomatis</h2>
    <p class="text-sm text-slate-500">MAC address yang mencoba mengirim data dengan API key valid tetapi belum terdaftar. Status <b>Online</b> = masih aktif menyapa dalam 5 menit terakhir.</p>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3">MAC Address</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Pertama Terlihat</th>
                <th class="px-4 py-3">Terakhir Terlihat</th>
                <th class="px-4 py-3">Jumlah Akses</th>
                <th class="px-4 py-3">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detected as $d)
            @php($online = $d->last_seen && $d->last_seen->diffInSeconds(now()) < 300)
            <tr class="border-b hover:bg-slate-50">
                <td class="px-4 py-3 font-mono text-xs">{{ $d->mac_address }}</td>
                <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $online ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                        {{ $online ? 'Online' : 'Offline' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-xs">{{ $d->first_seen?->format('d-m-Y H:i') ?? '-' }}</td>
                <td class="px-4 py-3 text-xs">{{ $d->last_seen?->format('d-m-Y H:i') ?? '-' }}</td>
                <td class="px-4 py-3">{{ $d->hits }}</td>
                <td class="px-4 py-3">
                    <div class="flex gap-1">
                        <a href="{{ route('devices.create', ['mac' => $d->mac_address]) }}"
                           class="rounded bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-700">Daftarkan</a>
                        <form method="POST" action="{{ route('devices.detected.delete', $d->id) }}"
                              onsubmit="return confirm('Hapus entri {{ $d->mac_address }} dari daftar terdeteksi?')">
                            @csrf
                            <button class="rounded bg-red-100 px-2 py-1 text-xs text-red-700 hover:bg-red-200">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Tidak ada perangkat tak dikenal.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
