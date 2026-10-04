@extends('layouts.app')
@section('title', 'Data Pelanggan')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" class="flex gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama / ID / WA / alamat..." class="w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <button class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"><i class="fas fa-magnifying-glass mr-1"></i>Cari</button>
    </form>
    <div class="flex gap-2">
        <form method="POST" action="{{ route('customers.broadcast') }}" onsubmit="return confirm('Kirim permintaan foto meteran ke semua pelanggan yang belum lapor?')">
            @csrf
            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"><i class="fas fa-bullhorn"></i> Minta Foto dari Warga</button>
        </form>
        <a href="{{ route('customers.broadcast-history') }}" class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-300"><i class="fas fa-clock-rotate-left mr-1"></i>Riwayat</a>
        <a href="{{ route('customers.export') }}" class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-300"><i class="fas fa-file-csv mr-1"></i>Export CSV</a>
        <a href="{{ route('customers.create') }}" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700"><i class="fas fa-plus mr-1"></i>Tambah Pelanggan</a>
    </div>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3">ID</th>
                <th class="px-4 py-3">Nama</th>
                <th class="px-4 py-3 hide-mobile">Alamat</th>
                <th class="px-4 py-3">WhatsApp</th>
                <th class="px-4 py-3 hide-mobile">LID</th>
                <th class="px-4 py-3">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customers as $c)
            <tr class="border-b hover:bg-slate-50">
                <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $c->customer_id }}</td>
                <td class="px-4 py-3">{{ $c->name }}</td>
                <td class="px-4 py-3 text-xs text-slate-500 hide-mobile">{{ $c->address ?? '-' }}</td>
                <td class="px-4 py-3 text-xs">{{ $c->phone ?? '-' }}</td>
                <td class="px-4 py-3 font-mono text-xs text-slate-400 hide-mobile">{{ $c->lid ? substr($c->lid, 0, 12) . '...' : '-' }}</td>
                <td class="px-4 py-3">
                    <div class="flex gap-1">
                        <a href="{{ route('customers.edit', $c->id) }}" class="rounded bg-sky-100 px-2 py-1 text-xs text-sky-700 hover:bg-sky-200"><i class="fas fa-pen mr-1"></i>Edit</a>
                        <form method="POST" action="{{ route('customers.delete', $c->id) }}" onsubmit="return confirm('Hapus pelanggan {{ $c->name }}?')">
                            @csrf
                            <button class="rounded bg-red-100 px-2 py-1 text-xs text-red-700 hover:bg-red-200"><i class="fas fa-trash-can mr-1"></i>Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Belum ada pelanggan.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="border-t px-4 py-3">{{ $customers->links() }}</div>
</div>

<div class="mt-4 rounded-xl bg-slate-100 p-4 text-xs text-slate-600">
    <i class="fas fa-info-circle"></i> <strong>Info:</strong> Setelah pelanggan didaftarkan, warga wajib mengirim pesan <code class="rounded bg-white px-1">AKTIVASI-{ID}</code> ke nomor server agar fitur kirim foto meteran aktif (LID WhatsApp akan tersambung otomatis).
    <form method="POST" action="{{ route('customers.import') }}" enctype="multipart/form-data" class="mt-2 flex items-center gap-2">
        @csrf
        <label class="flex flex-1 flex-col gap-1 rounded-lg border border-slate-300 bg-white px-3 py-2">
            <span class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                <i class="fas fa-file-arrow-up text-slate-400"></i> Pilih berkas CSV
            </span>
            <input type="file" name="file" accept=".csv,.txt" required class="text-xs">
        </label>
        <button class="rounded bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white"><i class="fas fa-upload mr-1"></i>Impor CSV</button>
    </form>
</div>
@endsection
