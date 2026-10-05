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
        <p class="mb-4 text-sm text-slate-500">Ambang &amp; warna gauge.</p>
        {{-- Tiap baris 3 kolom: input ANGKA (kiri, lebar) + LABEL (tengah) + input WARNA (kanan).
             Label Rendah/Sedang/Aman berada di KIRI swatch warna, sejajar horizontal.
             Nilai default mengikuti IndicatorSetting::getSettings() (30 / #e74c3c, 70 / #f39c12, #27ae60). --}}
        <form method="POST" action="{{ route('settings.display') }}" class="space-y-2">
            @csrf
            <div class="space-y-2">
                <div class="grid items-center gap-2" style="grid-template-columns:1fr 58px 58px">
                    <input type="number" name="threshold_low" required min="0" max="100"
                           value="{{ $settings['threshold_low'] }}" aria-label="Ambang rendah (%)" title="Ambang rendah (%)"
                           class="h-10 w-full rounded-lg border border-slate-300 px-2 text-center text-sm">
                    <label for="c-low" class="text-xs font-medium text-slate-600">Rendah</label>
                    <input id="c-low" type="color" name="color_low" value="{{ $settings['color_low'] }}"
                           data-sw="sw-low"
                           class="h-10 w-full cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
                </div>
                <div class="grid items-center gap-2" style="grid-template-columns:1fr 58px 58px">
                    <input type="number" name="threshold_medium" required min="0" max="100"
                           value="{{ $settings['threshold_medium'] }}" aria-label="Ambang sedang (%)" title="Ambang sedang (%)"
                           class="h-10 w-full rounded-lg border border-slate-300 px-2 text-center text-sm">
                    <label for="c-mid" class="text-xs font-medium text-slate-600">Sedang</label>
                    <input id="c-mid" type="color" name="color_medium" value="{{ $settings['color_medium'] }}"
                           data-sw="sw-mid"
                           class="h-10 w-full cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
                </div>
                <div class="grid items-center gap-2" style="grid-template-columns:1fr 58px 58px">
                    <div aria-hidden="true"></div>
                    <label for="c-high" class="text-xs font-medium text-slate-600">Aman</label>
                    <input id="c-high" type="color" name="color_high" value="{{ $settings['color_high'] }}"
                           data-sw="sw-high"
                           class="h-10 w-full cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg bg-slate-50 px-3 py-2 text-[11px] text-slate-600">
                <span class="font-medium">Pratinjau:</span>
                <span class="inline-flex items-center gap-1"><span id="sw-low" class="inline-block h-3.5 w-3.5 rounded" style="background: {{ $settings['color_low'] }}"></span> rendah</span>
                <span class="inline-flex items-center gap-1"><span id="sw-mid" class="inline-block h-3.5 w-3.5 rounded" style="background: {{ $settings['color_medium'] }}"></span> sedang</span>
                <span class="inline-flex items-center gap-1"><span id="sw-high" class="inline-block h-3.5 w-3.5 rounded" style="background: {{ $settings['color_high'] }}"></span> aman</span>
            </div>
            {{-- Default di kiri, Simpan di ujung kanan --}}
            <div class="mt-3 flex items-center justify-between gap-2">
                <button type="button" id="btn-default"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    <i class="fas fa-rotate-left"></i>Default
                </button>
                <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                    <i class="fas fa-floppy-disk"></i>Simpan
                </button>
            </div>
        </form>
        <script>
            // Nilai default = IndicatorSetting::getSettings() (30 / #e74c3c, 70 / #f39c12, #27ae60)
            const GAUGE_DEFAULTS = { threshold_low: 30, color_low: '#e74c3c', threshold_medium: 70, color_medium: '#f39c12', color_high: '#27ae60' };
            // Pratinjau warna ikut berubah begitu operator memilih warna (tanpa reload)
            document.querySelectorAll('input[type="color"][data-sw]').forEach(function (el) {
                el.addEventListener('input', function () {
                    const sw = document.getElementById(el.dataset.sw);
                    if (sw) sw.style.background = el.value;
                });
            });
            // Tombol Default: kembalikan form ke nilai bawaan (perlu klik Simpan untuk menerapkan)
            const btnDefault = document.getElementById('btn-default');
            if (btnDefault) {
                btnDefault.addEventListener('click', function () {
                    const form = btnDefault.closest('form');
                    Object.keys(GAUGE_DEFAULTS).forEach(function (name) {
                        const input = form.querySelector('[name="' + name + '"]');
                        if (!input) return;
                        input.value = GAUGE_DEFAULTS[name];
                        const sw = input.dataset ? document.getElementById(input.dataset.sw) : null;
                        if (sw) sw.style.background = input.value;
                    });
                });
            }
        </script>
    </div>
{{-- ================= KANAN (2/3): TEMPLATE GAUGE ================= --}}
    @if($canTemplates)
    <div class="rounded-xl bg-white p-5 shadow lg:col-span-2">
        <h2 class="mb-1 flex items-center gap-2 font-semibold">
            <i class="fas fa-magic text-violet-600"></i> Template Gauge
        </h2>
        <p class="mb-3 text-sm text-slate-500">Pratinjau pada level {{ $previewPercent }}%.</p>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($templates as $t)
            <div class="flex flex-col rounded-xl border p-2 {{ $activeId === $t->name ? 'border-sky-400 bg-sky-50/60 ring-1 ring-sky-300' : 'border-slate-200' }}">
                <iframe title="Pratinjau {{ $t->name }}" sandbox="allow-scripts" loading="lazy"
                        class="h-24 w-full flex-1 rounded-lg border border-slate-200 bg-white"
                        srcdoc="{{ $t->preview_srcdoc }}"></iframe>
                <div class="mt-2 flex items-center justify-between gap-1">
                    <span class="flex min-w-0 items-center gap-1 text-xs font-semibold">
                        <span class="truncate">{{ $t->name }}</span>
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