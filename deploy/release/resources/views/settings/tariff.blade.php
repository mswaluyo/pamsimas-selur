@extends('layouts.app')
@section('title', 'Pengaturan Tarif')

@section('content')
<div class="mx-auto max-w-lg rounded-xl bg-white p-6 shadow">
    <h2 class="mb-1 font-semibold">Tarif Air</h2>
    <p class="mb-4 text-sm text-slate-500">Harga per m³ dan biaya administrasi bulanan dipakai BillingService untuk menghitung tagihan otomatis.</p>
    <form method="POST" action="{{ route('settings.tariff') }}" class="space-y-4">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium">Harga Air (Rp / m³)</label>
            <input type="number" name="water_price" required min="0" step="0.01" value="{{ $settings['water_price'] }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Biaya Administrasi Bulanan (Rp)</label>
            <input type="number" name="admin_fee" required min="0" step="0.01" value="{{ $settings['admin_fee'] }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button class="rounded-lg bg-sky-600 px-5 py-2.5 font-semibold text-white hover:bg-sky-700">Simpan Tarif</button>
    </form>
</div>
@endsection
