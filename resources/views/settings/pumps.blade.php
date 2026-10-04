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
                        <th class="px-4 py-3 hide-mobile">Daya</th>
                        <th class="px-4 py-3">Durasi ON/OFF (dtk)</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pumps as $p)
                    <tr class="border-b">
                        <td class="px-4 py-3 font-medium">{{ $p->pump_name }}</td>
                        <td class="px-4 py-3">{{ $p->flow_rate_lps }}</td>
                        <td class="px-4 py-3 hide-mobile">{{ $p->power_watt }} W @if($p->power_hp)({{ $p->power_hp }} HP)@endif</td>
                        <td class="px-4 py-3 text-xs">{{ $p->on_duration_seconds }} / {{ $p->off_duration_seconds }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-1">
                                <button type="button" onclick="editPump('{{ $p->id }}', '{{ addslashes($p->pump_name) }}', '{{ $p->flow_rate_lps }}', '{{ $p->power_hp }}', '{{ $p->power_watt }}', '{{ $p->delay_seconds }}', '{{ $p->on_duration_seconds }}', '{{ $p->off_duration_seconds }}')"
                                        class="rounded bg-amber-100 px-2 py-1 text-xs text-amber-700 hover:bg-amber-200">Edit</button>
                                <form method="POST" action="{{ route('settings.pumps') }}/{{ $p->id }}/delete" onsubmit="return confirm('Hapus pompa ini?')">
                                    @csrf
                                    <button class="rounded bg-red-100 px-2 py-1 text-xs text-red-700 hover:bg-red-200"><i class="fas fa-trash-can mr-1"></i>Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Belum ada pompa.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 font-semibold">Tambah Pompa</h2>
        <form method="POST" action="{{ route('settings.pumps') }}" class="space-y-3">
            @csrf
            <div>
                <label for="add-pump-name" class="mb-1 block text-sm font-medium">Nama Pompa</label>
                <input type="text" id="add-pump-name" name="pump_name" required placeholder="cth: Pompa Mbaran" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="add-flow-rate" class="mb-1 block text-sm font-medium">Debit Air (liter/detik)</label>
                <input type="number" id="add-flow-rate" step="0.1" name="flow_rate_lps" placeholder="cth: 0.6" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="add-power-watt" class="mb-1 block text-sm font-medium">Daya Listrik (Watt)</label>
                <input type="number" id="add-power-watt" name="power_watt" placeholder="cth: 1300" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="add-on-duration" class="mb-1 block text-sm font-medium">Durasi ON (detik)</label>
                <input type="number" id="add-on-duration" name="on_duration_seconds" required min="1" placeholder="cth: 600" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Batas nyala maksimum tiap siklus (proteksi mesin), dikirim ke perangkat sebagai <code>on_duration</code>.</p>
            </div>
            <div>
                <label for="add-off-duration" class="mb-1 block text-sm font-medium">Durasi OFF / Istirahat (detik)</label>
                <input type="number" id="add-off-duration" name="off_duration_seconds" required min="1" placeholder="cth: 300" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="w-full rounded-lg bg-sky-600 py-2 font-semibold text-white hover:bg-sky-700">Simpan</button>
        </form>
    </div>
</div>

{{-- Modal Edit Pompa --}}
<div id="pumpModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 font-semibold">Edit Pompa</h2>
        <form id="pumpEditForm" method="POST" class="space-y-3">
            @csrf
            <div>
                <label for="pump_name" class="mb-1 block text-sm font-medium">Nama Pompa</label>
                <input type="text" id="pump_name" name="pump_name" required placeholder="cth: Pompa Mbaran" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="flow_rate_lps" class="mb-1 block text-sm font-medium">Debit Air (liter/detik)</label>
                <input type="number" step="0.01" id="flow_rate_lps" name="flow_rate_lps" placeholder="cth: 0.6" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="power_hp" class="mb-1 block text-sm font-medium">Daya (HP / Horsepower)</label>
                <input type="number" step="0.1" id="power_hp" name="power_hp" placeholder="cth: 1.5" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="power_watt" class="mb-1 block text-sm font-medium">Daya (Watt)</label>
                <input type="number" id="power_watt" name="power_watt" placeholder="cth: 1300" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="delay_seconds" class="mb-1 block text-sm font-medium">Delay Start (detik)</label>
                <input type="number" id="delay_seconds" name="delay_seconds" min="0" placeholder="cth: 20" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Jeda sebelum pompa menyala setelah level menyentuh ambang trigger.</p>
            </div>
            <div>
                <label for="on_duration_seconds" class="mb-1 block text-sm font-medium">Durasi ON maksimum (detik)</label>
                <input type="number" id="on_duration_seconds" name="on_duration_seconds" required min="1" placeholder="cth: 660" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Dikirim ke perangkat sebagai <code>on_duration</code>: setelah selama ini pompa dipaksa mati (safety cut-off) lalu istirahat selama Durasi OFF, lalu menyala lagi. Saat sensor tidak terbaca, nilai ini yang menentukan panjang tiap siklus pengisian.</p>
            </div>
            <div>
                <label for="off_duration_seconds" class="mb-1 block text-sm font-medium">Durasi OFF / Istirahat (detik)</label>
                <input type="number" id="off_duration_seconds" name="off_duration_seconds" required min="1" placeholder="cth: 600" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Dikirim ke perangkat sebagai <code>off_duration</code>: masa tunggu sebelum pompa boleh menyala lagi.</p>
            </div>
            <div class="flex gap-2">
                <button class="w-full rounded-lg bg-sky-600 py-2 font-semibold text-white hover:bg-sky-700">Simpan Perubahan</button>
                <button type="button" onclick="closeModal('pumpModal')" class="w-full rounded-lg bg-slate-200 py-2 font-semibold text-slate-700 hover:bg-slate-300">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function editPump(id, name, flow, hp, watt, delay, on, off) {
    const f = document.getElementById('pumpEditForm');
    f.action = '{{ route('settings.pumps') }}/' + id;
    document.getElementById('pump_name').value = name;
    document.getElementById('flow_rate_lps').value = flow;
    document.getElementById('power_hp').value = hp;
    document.getElementById('power_watt').value = watt;
    document.getElementById('delay_seconds').value = delay;
    document.getElementById('on_duration_seconds').value = on;
    document.getElementById('off_duration_seconds').value = off;
    openModal('pumpModal');
}
function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('flex'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }
</script>
@endsection
