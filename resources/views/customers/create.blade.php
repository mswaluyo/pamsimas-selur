@extends('layouts.app')
@section('title', 'Tambah Pelanggan')

@section('content')
<div class="mx-auto max-w-lg rounded-xl bg-white p-6 shadow">
    <h2 class="mb-4 font-semibold">Tambah Pelanggan Baru</h2>
    <form method="POST" action="{{ route('customers.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium">ID Pelanggan (format PTA-XXXX)</label>
            <input type="text" name="customer_id" required value="{{ old('customer_id') }}" placeholder="PTA-0001" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Nama Lengkap</label>
            <input type="text" name="name" required value="{{ old('name') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Alamat</label>
            <textarea name="address" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('address') }}</textarea>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Nomor WhatsApp (62xxx)</label>
            <input type="text" name="phone" value="{{ old('phone') }}" placeholder="628xxxx" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">LID (opsional — terisi otomatis saat aktivasi WA)</label>
            <input type="text" name="lid" value="{{ old('lid') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
        </div>
        <button class="rounded-lg bg-sky-600 px-5 py-2.5 font-semibold text-white hover:bg-sky-700">Simpan</button>
    </form>
</div>
@endsection
