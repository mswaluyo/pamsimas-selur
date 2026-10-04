@extends('layouts.app')
@section('title', 'Tampilan')

@section('content')
{{-- Halaman "Tampilan": Pengaturan Tampilan (kiri) + Template Gauge (kanan). --}}
<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

    {{-- ================= KIRI (1/3): PENGATURAN TAMPILAN ================= --}}
    <div class="rounded-xl bg-white p-5 shadow lg:col-span-1">
        <h2 class="mb-1 flex items-center gap-2 font-semibold">
            <i class="fas fa-tint text-sky-600"></i> Tampilan
        </h2>
        <p class="mb-4 text-sm text-slate-500">Ambang & warna gauge.</p>
        <form method="POST" action="{{ route('settings.display') }}" class="space-y-3">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium">Ambang Rendah (%)</label>
                    <input type="number" name="threshold_low" required min="0" max="100" value="{{ $settings['threshold_low'] }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Warna Rendah</label>
                    <input type="color" name="color_low" value="{{ $settings['color_low'] }}" class="h-10 w-full rounded-lg border border-slate-300">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Ambang Sedang (%)</label>
                    <input type="number" name="threshold_medium" required min="0" max="100" value="{{ $settings['threshold_medium'] }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Warna Sedang</label>
                    <input type="color" name="color_medium" value="{{ $settings['color_medium'] }}" class="h-10 w-full rounded-lg border border-slate-300">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Warna Aman</label>
                    <input type="color" name="color_high" value="{{ $settings['color_high'] }}" class="h-10 w-full rounded-lg border border-slate-300">
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                <span>Pratinjau:</span>
                <span class="inline-flex items-center gap-1"><span class="inline-block h-4 w-4 rounded" style="background: {{ $settings['color_low'] }}"></span> rendah</span>
                <span class="inline-flex items-center gap-1"><span class="inline-block h-4 w-4 rounded" style="background: {{ $settings['color_medium'] }}"></span> sedang</span>
                <span class="inline-flex items-center gap-1"><span class="inline-block h-4 w-4 rounded" style="background: {{ $settings['color_high'] }}"></span> aman</span>
            </div>
            <button class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700"><i class="fas fa-floppy-disk mr-1"></i>Simpan</button>
        </form>
    </div>
{{-- ================= KANAN (2/3): TEMPLATE GAUGE ================= --}}
    @if($canTemplates)
    <div class="rounded-xl bg-white p-5 shadow lg:col-span-2">
        <h2 class="mb-1 flex items-center gap-2 font-semibold">
            <i class="fas fa-magic text-violet-600"></i> Template Gauge
        </h2>
        <p class="mb-3 text-sm text-slate-500">Pratinjau pada level {{ $previewPercent }}%.</p>
        <div class="grid grid-cols-2 gap-3 xl:grid-cols-3">
            @foreach($templates as $t)
            <div class="rounded-xl border p-2 {{ $activeId === $t->name ? 'border-sky-400 bg-sky-50/60 ring-1 ring-sky-300' : 'border-slate-200' }}">
                <iframe title="Pratinjau {{ $t->name }}" sandbox="allow-scripts" loading="lazy"
                        class="h-28 w-full rounded-lg border border-slate-200 bg-white"
                        srcdoc="{{ $t->preview_srcdoc }}"></iframe>
                <div class="mt-2 flex items-center justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-1 text-xs font-semibold">
                        <span class="truncate">{{ $t->name }}</span>
                        @if($t->needs_library)
                            <i class="fas fa-cloud-download shrink-0 text-sky-500"
                               title="Memakai pustaka luar (DevExtreme + jQuery) yang diambil dari CDN saat gauge ini dipakai — perlu koneksi internet"></i>
                        @endif
                    </span>
                    @if($canTemplatesEdit)
                        @if($activeId === $t->name)
                            {{-- Slide ON: template ini sedang dipakai (tidak bisa dimatikan) --}}
                            <span class="flex shrink-0 items-center gap-1.5" role="status"
                                  title="{{ $t->name }} sedang aktif">
                                <span class="text-[10px] font-semibold uppercase tracking-wide text-emerald-600">On</span>
                                <span class="flex h-[18px] w-8 items-center rounded-full bg-emerald-500 p-0.5 shadow-sm">
                                    <span class="ml-auto h-3.5 w-3.5 rounded-full bg-white shadow"></span>
                                </span>
                            </span>
                        @else
                            {{-- Slide OFF: klik untuk mengaktifkan --}}
                            <form method="POST" action="{{ route('templates.activate', $t->id) }}" class="shrink-0">
                                @csrf
                                <button type="submit" title="Aktifkan {{ $t->name }}" aria-label="Aktifkan {{ $t->name }}"
                                        class="flex h-[18px] w-8 items-center rounded-full bg-slate-300 p-0.5 shadow-sm transition hover:bg-slate-400">
                                    <span class="h-3.5 w-3.5 rounded-full bg-white shadow"></span>
                                </button>
                            </form>
                            @unless($t->is_core)
                            <form method="POST" action="{{ route('templates.destroy', $t->id) }}" class="shrink-0" onsubmit="return confirm('Hapus template?')">
                                @csrf
                                <button title="Hapus {{ $t->name }}" aria-label="Hapus {{ $t->name }}"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-red-700 hover:bg-red-200">
                                    <i class="fas fa-trash-can"></i>
                                </button>
                            </form>
                            @endunless
                        @endif
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection