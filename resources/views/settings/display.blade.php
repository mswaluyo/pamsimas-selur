@extends('layouts.app')
@section('title', 'Tampilan')

@section('content')
{{-- Halaman "Tampilan": Pengaturan Tampilan (kiri) + Template Gauge (kanan). --}}
<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

    {{-- ================= KIRI: PENGATURAN TAMPILAN ================= --}}
    <div class="rounded-xl bg-white p-5 shadow">
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
{{-- ================= KANAN: TEMPLATE GAUGE ================= --}}
    @if($canTemplates)
    <div class="rounded-xl bg-white p-5 shadow">
        <h2 class="mb-1 flex items-center gap-2 font-semibold">
            <i class="fas fa-magic text-violet-600"></i> Template Gauge
        </h2>
        <p class="mb-3 text-sm text-slate-500">Pratinjau gauge pada level {{ $previewPercent }}%. Klik Aktifkan untuk dipakai.</p>
        <div class="space-y-2">
            @foreach($templates as $t)
            <div class="flex items-center gap-3 rounded-lg border p-2 {{ $activeId === $t->name ? 'border-sky-400 bg-sky-50/60 ring-1 ring-sky-300' : 'border-slate-200' }}">
                <iframe title="Pratinjau {{ $t->name }}" sandbox="allow-scripts" loading="lazy"
                        class="h-24 w-24 shrink-0 rounded-md border border-slate-200 bg-white"
                        srcdoc="{{ $t->preview_srcdoc }}"></iframe>
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 truncate text-sm font-semibold">
                        {{ $t->name }}
                        @if($activeId === $t->name)
                            <span class="rounded-full bg-sky-600 px-2 py-0.5 text-[10px] font-semibold text-white">Aktif</span>
                        @endif
                    </p>
                    @if($t->needs_library)
                        <p class="mt-0.5 text-[11px] leading-tight text-amber-700">
                            <i class="fas fa-triangle-exclamation"></i>
                            Butuh DevExtreme + jQuery yang belum tersedia di aplikasi, jadi gauge ini kosong.
                        </p>
                    @elseif($t->description && $t->description !== $t->name)
                        <p class="mt-0.5 line-clamp-2 text-[11px] leading-tight text-slate-500">{{ $t->description }}</p>
                    @endif
                </div>
                @if($canTemplatesEdit)
                    <div class="flex shrink-0 flex-col gap-1">
                        @unless($activeId === $t->name)
                        <form method="POST" action="{{ route('templates.activate', $t->id) }}">
                            @csrf
                            <button class="rounded bg-emerald-100 px-2 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-200"><i class="fas fa-toggle-on mr-1"></i>Aktifkan</button>
                        </form>
                        @endunless
                        @unless($t->is_core)
                        <form method="POST" action="{{ route('templates.destroy', $t->id) }}" onsubmit="return confirm('Hapus template?')">
                            @csrf
                            <button class="rounded bg-red-100 px-2 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-200"><i class="fas fa-trash-can mr-1"></i>Hapus</button>
                        </form>
                        @endunless
                    </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection