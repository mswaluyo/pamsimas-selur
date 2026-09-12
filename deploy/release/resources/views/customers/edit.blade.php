@extends('layouts.app')
@section('title', 'Edit Pelanggan')

@section('content')
<div class="mx-auto max-w-lg rounded-xl bg-white p-6 shadow">
    <h2 class="mb-4 font-semibold">Edit Pelanggan: {{ $customer->name }}</h2>
    <form method="POST" action="{{ route('customers.update', $customer->id) }}" class="space-y-4">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium">ID Pelanggan</label>
            <input type="text" name="customer_id" required value="{{ $customer->customer_id }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Nama Lengkap</label>
            <input type="text" name="name" required value="{{ $customer->name }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Alamat</label>
            <textarea name="address" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $customer->address }}</textarea>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Nomor WhatsApp</label>
            <input type="text" name="phone" value="{{ $customer->phone }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">LID</label>
            <input type="text" name="lid" value="{{ $customer->lid }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
        </div>
        <button class="rounded-lg bg-sky-600 px-5 py-2.5 font-semibold text-white hover:bg-sky-700">Simpan Perubahan</button>
    </form>
</div>
@endsection
