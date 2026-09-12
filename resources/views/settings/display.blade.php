@extends('layouts.app')
@section('title', 'Tampilan & Indikator')

@section('content')
<div class="mx-auto max-w-lg rounded-xl bg-white p-6 shadow">
    <h2 class="mb-1 font-semibold">Indikator Level Air</h2>
    <p class="mb-4 text-sm text-slate-500">Ambang & warna untuk gauge di dashboard, plus template aktif.</p>
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
            <div>
                <label class="mb-1 block text-sm font-medium">Template Aktif</label>
                <select name="active_template_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach($templates as $t)
                    <option value="{{ $t->name }}" @if($settings['active_template_id'] === $t->name)selected@endif>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="rounded-lg bg-slate-50 p-4" style="background: {{ $settings['color_low'] }}22">
            <p class="text-xs text-slate-500">Pratinjau warna:
                <span class="ml-2 inline-block h-4 w-4 rounded" style="background: {{ $settings['color_low'] }}"></span> rendah
                <span class="ml-2 inline-block h-4 w-4 rounded" style="background: {{ $settings['color_medium'] }}"></span> sedang
                <span class="ml-2 inline-block h-4 w-4 rounded" style="background: {{ $settings['color_high'] }}"></span> aman
            </p>
        </div>
        <button class="rounded-lg bg-sky-600 px-5 py-2.5 font-semibold text-white hover:bg-sky-700">Simpan Tampilan</button>
    </form>
</div>
@endsection
