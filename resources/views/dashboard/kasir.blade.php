@extends('layouts.app')
@section('title', 'Dasbor Kasir')

@section('content')
<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <a href="{{ route('meter.index') }}" class="rounded-xl bg-white p-6 shadow transition hover:shadow-lg">
        <span class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-sky-500 text-2xl">🔍</span>
        <h2 class="text-lg font-bold">Kasir Meter</h2>
        <p class="mt-1 text-sm text-slate-500">Validasi antrean foto meteran warga & pencatatan manual bulan ini.</p>
    </a>
    <a href="{{ route('payment.index') }}" class="rounded-xl bg-white p-6 shadow transition hover:shadow-lg">
        <span class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500 text-2xl">💰</span>
        <h2 class="text-lg font-bold">Pembayaran Tagihan</h2>
        <p class="mt-1 text-sm text-slate-500">Proses pembayaran tagihan warga & cetak/kirim kwitansi WhatsApp.</p>
    </a>
    <a href="{{ route('customers.index') }}" class="rounded-xl bg-white p-6 shadow transition hover:shadow-lg">
        <span class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500 text-2xl">👥</span>
        <h2 class="text-lg font-bold">Data Pelanggan</h2>
        <p class="mt-1 text-sm text-slate-500">Kelola data pelanggan, nomor WhatsApp, dan broadcast permintaan foto meteran.</p>
    </a>
    <div class="rounded-xl bg-white p-6 shadow">
        <span class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-violet-500 text-2xl">💡</span>
        <h2 class="text-lg font-bold">Siklus Bulanan</h2>
        <p class="mt-1 text-sm text-slate-500">1. Minta foto warga → 2. Validasi antrean → 3. Proses pembayaran.</p>
    </div>
</div>
@endsection
