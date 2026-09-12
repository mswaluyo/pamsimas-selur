@extends('layouts.app')
@section('title', 'Template Gauge')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="font-semibold">Template Gauge Dashboard</h2>
    <form method="POST" action="{{ route('templates.index') }}" class="flex gap-2">
        @csrf
        <input type="text" name="name" required placeholder="Nama template baru" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input type="text" name="description" placeholder="Deskripsi" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <button class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">+ Tambah</button>
    </form>
</div>

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach($templates as $t)
    <div class="rounded-xl bg-white p-5 shadow {{ $activeId === $t->name ? 'ring-2 ring-sky-500' : '' }}">
        <div class="mb-2 flex items-center justify-between">
            <h3 class="font-semibold">{{ $t->name }}</h3>
            @if($activeId === $t->name)
            <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-700">Aktif</span>
            @endif
        </div>
        <p class="mb-3 min-h-[2.5rem] text-xs text-slate-500">{{ $t->description }}</p>
        <div class="flex gap-2">
            @unless($activeId === $t->name)
            <form method="POST" action="{{ route('templates.activate', $t->id) }}">
                @csrf
                <button class="rounded bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-200">Aktifkan</button>
            </form>
            @endunless
            @unless($t->is_core)
            <form method="POST" action="{{ route('templates.destroy', $t->id) }}" onsubmit="return confirm('Hapus template?')">
                @csrf
                <button class="rounded bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-200">Hapus</button>
            </form>
            @endunless
        </div>
    </div>
    @endforeach
</div>
@endsection
