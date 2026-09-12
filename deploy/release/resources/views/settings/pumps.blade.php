@extends('layouts.app')
@section('title', 'Pengaturan Pompa')

@section('content')
<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="overflow-x-auto rounded-xl bg-white shadow">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-xs uppercase text-slate-500">
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Debit (L/s)</th>
                        <th class="px-4 py-3">Daya</th>
                        <th class="px-4 py-3">Durasi ON/OFF (dtk)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pumps as $p)
                    <tr class="border-b">
                        <td class="px-4 py-3 font-medium">{{ $p->pump_name }}</td>
                        <td class="px-4 py-3">{{ $p->flow_rate_lps }}</td>
                        <td class="px-4 py-3">{{ $p->power_watt }} W @if($p->power_hp)({{ $p->power_hp }} HP)@endif</td>
                        <td class="px-4 py-3 text-xs">{{ $p->on_duration_seconds }} / {{ $p->off_duration_seconds }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Belum ada pompa.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 font-semibold">Tambah Pompa</h2>
        <form method="POST" action="{{ route('settings.pumps') }}" class="space-y-3">
            @csrf
            <input type="text" name="pump_name" required placeholder="Nama pompa" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="number" step="0.1" name="flow_rate_lps" placeholder="Debit (L/s)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="number" name="power_watt" placeholder="Daya (Watt)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="number" name="on_duration_seconds" required min="1" placeholder="Durasi ON (detik)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="number" name="off_duration_seconds" required min="1" placeholder="Durasi OFF (detik)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="w-full rounded-lg bg-sky-600 py-2 font-semibold text-white hover:bg-sky-700">Simpan</button>
        </form>
    </div>
</div>
@endsection
