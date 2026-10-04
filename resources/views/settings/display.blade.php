@extends('layouts.app')
@section('title', 'Tampilan')

@section('content')
{{-- Halaman "Tampilan": gabungan Indikator Level Air + Template Gauge (satu menu). --}}
<div class="mx-auto max-w-4xl space-y-4">

    {{-- ================= 1. INDIKATOR LEVEL AIR ================= --}}
    <div class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-1 flex items-center gap-2 font-semibold">
            <i class="fas fa-tint text-sky-600"></i> Indikator Level Air
        </h2>
        <p class="mb-4 text-sm text-slate-500">Ambang & warna untuk gauge di dashboard dan halaman detail perangkat.</p>
        <form method="POST" action="{{ route('settings.display') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">Ambang Rendah (%)</label>
                    <input type="number" name="threshold_low" required min="0" max="100" value="{{ $settings['threshold_low'] }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Warna Rendah</label>
                    <input type="color" name="color_low" value="{{ $settings['color_low'] }}" class="h-10 w-full rounded-lg border border-slate-300">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Ambang Sedang (%)</label>
                    <input type="number" name="threshold_medium" required min="0" max="100" value="{{ $settings['threshold_medium'] }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Warna Sedang</label>
                    <input type="color" name="color_medium" value="{{ $settings['color_medium'] }}" class="h-10 w-full rounded-lg border border-slate-300">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Warna Aman</label>
                    <input type="color" name="color_high" value="{{ $settings['color_high'] }}" class="h-10 w-full rounded-lg border border-slate-300">
                </div>
            </div>
            <div class="rounded-lg bg-slate-50 p-4" style="background: {{ $settings['color_low'] }}22">
                <p class="text-xs text-slate-500">Pratinjau warna:
                    <span class="ml-2 inline-block h-4 w-4 rounded" style="background: {{ $settings['color_low'] }}"></span> rendah
                    <span class="ml-2 inline-block h-4 w-4 rounded" style="background: {{ $settings['color_medium'] }}"></span> sedang
                    <span class="ml-2 inline-block h-4 w-4 rounded" style="background: {{ $settings['color_high'] }}"></span> aman
                </p>
            </div>
            <button class="rounded-lg bg-sky-600 px-5 py-2.5 font-semibold text-white hover:bg-sky-700"><i class="fas fa-floppy-disk mr-1"></i>Simpan Tampilan</button>
        </form>
    </div>
@if($canTemplates)
        {{-- ================= 2. TEMPLATE GAUGE ================= --}}
        <div class="rounded-xl bg-white p-6 shadow">
            <h2 class="mb-1 flex items-center gap-2 font-semibold">
                <i class="fas fa-magic text-violet-600"></i> Template Gauge
            </h2>
            <p class="mb-4 text-sm text-slate-500">Template yang dipakai untuk gauge. Template aktif ditandai dan bisa diganti di sini.</p>

            @if($canTemplatesEdit)
                <form method="POST" action="{{ route('templates.index') }}" class="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                    @csrf
                    <input type="text" name="name" required placeholder="Nama template baru" aria-label="Nama template baru" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="text" name="description" placeholder="Deskripsi (opsional)" aria-label="Deskripsi template" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700"><i class="fas fa-plus mr-1"></i>Tambah Template</button>
                </form>
            @endif

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($templates as $t)
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow {{ $activeId === $t->name ? 'ring-2 ring-sky-500' : '' }}">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h3 class="font-semibold">{{ $t->name }}</h3>
                        @if($activeId === $t->name)
                            <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-700">Aktif</span>
                        @endif
                    </div>
                    <p class="mb-3 min-h-[2.5rem] text-xs text-slate-500">{{ $t->description ?: 'Tanpa deskripsi.' }}</p>
                    <div class="flex flex-wrap gap-2">
                        @if($canTemplatesEdit)
                            @unless($activeId === $t->name)
                            <form method="POST" action="{{ route('templates.activate', $t->id) }}">
                                @csrf
                                <button class="rounded bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-200"><i class="fas fa-toggle-on mr-1"></i>Aktifkan</button>
                            </form>
                            @endunless
                            @unless($t->is_core)
                            <form method="POST" action="{{ route('templates.destroy', $t->id) }}" onsubmit="return confirm('Hapus template?')">
                                @csrf
                                <button class="rounded bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-200"><i class="fas fa-trash-can mr-1"></i>Hapus</button>
                            </form>
                            @endunless
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    @endif

</div>
@endsection
