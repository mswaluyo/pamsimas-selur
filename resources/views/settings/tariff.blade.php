@extends('layouts.app')
@section('title', 'Pengaturan Tarif')

@section('content')
<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="overflow-x-auto rounded-xl bg-white shadow">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-xs uppercase text-slate-500">
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Bulan Berubah</th>
                        <th class="px-4 py-3">Harga Air (Rp / m³)</th>
                        <th class="px-4 py-3">Biaya Admin (Rp)</th>
                        <th class="px-4 py-3">Oleh</th>
                    </tr>
                </thead>
                <tbody>
                @php
                    $bulanId = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                @endphp
                @forelse ($history as $h)
                    <tr class="border-b hover:bg-slate-50">
                        <td class="px-4 py-3 text-xs">{{ $h->created_at?->format('d-m-Y H:i') ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                                {{ $bulanId[(int) ($h->created_at?->format('n'))] ?? '-' }} {{ $h->created_at?->format('Y') }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($h->old_water_price !== null && abs($h->old_water_price - $h->water_price) >= 0.005)
                                <span class="text-slate-400 line-through">{{ number_format($h->old_water_price, 0, ',', '.') }}</span>
                                <span class="mx-1 text-slate-400"><i class="fas fa-arrow-right"></i></span>
                            @endif
                            <span class="font-semibold">{{ number_format($h->water_price, 0, ',', '.') }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($h->old_admin_fee !== null && abs($h->old_admin_fee - $h->admin_fee) >= 0.005)
                                <span class="text-slate-400 line-through">{{ number_format($h->old_admin_fee, 0, ',', '.') }}</span>
                                <span class="mx-1 text-slate-400"><i class="fas fa-arrow-right"></i></span>
                            @endif
                            <span class="font-semibold">{{ number_format($h->admin_fee, 0, ',', '.') }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs">{{ $h->changed_by ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">Belum ada perubahan tarif tercatat.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 font-semibold">Tarif Air</h2>
        <p class="mb-4 text-sm text-slate-500">Harga per m³ dan biaya administrasi bulanan dipakai BillingService untuk menghitung tagihan otomatis.</p>
        <form method="POST" action="{{ route('settings.tariff') }}" class="space-y-3">
            @csrf
            <div>
                <label for="water_price" class="mb-1 block text-sm font-medium">Harga Air (Rp per m³)</label>
                <input type="number" id="water_price" name="water_price" required min="0" step="0.01" value="{{ $settings['water_price'] }}"
                       placeholder="cth: 5000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="admin_fee" class="mb-1 block text-sm font-medium">Biaya Administrasi Bulanan (Rp)</label>
                <input type="number" id="admin_fee" name="admin_fee" required min="0" step="0.01" value="{{ $settings['admin_fee'] }}"
                       placeholder="cth: 5000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="w-full rounded-lg bg-sky-600 py-2 font-semibold text-white hover:bg-sky-700">Simpan Tarif</button>
        </form>
    </div>
</div>
@endsection
