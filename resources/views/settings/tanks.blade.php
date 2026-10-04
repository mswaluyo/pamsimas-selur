@extends('layouts.app')
@section('title', 'Pengaturan Tangki')

@section('content')
<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="overflow-x-auto rounded-xl bg-white shadow">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-xs uppercase text-slate-500">
                        <th class="px-4 py-3">Nama Tangki</th>
                        <th class="px-4 py-3 hide-mobile">Bentuk</th>
                        <th class="px-4 py-3 hide-mobile">Dimensi</th>
                        <th class="px-4 py-3">Tinggi (cm)</th>
                        <th class="px-4 py-3">Dipakai Perangkat</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tanks as $t)
                    <tr class="border-b">
                        <td class="px-4 py-3 font-medium">{{ $t->tank_name }}</td>
                        <td class="px-4 py-3 hide-mobile">{{ ucfirst($t->tank_shape) }}</td>
                        <td class="px-4 py-3 text-xs hide-mobile">
                            @if($t->tank_shape === 'kotak')
                                {{ $t->rect_length ?? '-' }} × {{ $t->rect_width ?? '-' }} cm
                            @elseif($t->tank_shape === 'bulat')
                                ⌀ {{ $t->circ_diameter ?? '-' }} cm
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $t->height }}</td>
                        <td class="px-4 py-3">{{ $t->devices_count }} perangkat</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-1">
                                <button type="button" onclick="editTank('{{ $t->id }}', '{{ addslashes($t->tank_name) }}', '{{ $t->tank_shape }}', '{{ $t->height }}', '{{ $t->rect_length ?? '' }}', '{{ $t->rect_width ?? '' }}', '{{ $t->circ_diameter ?? '' }}')"
                                        class="rounded bg-amber-100 px-2 py-1 text-xs text-amber-700 hover:bg-amber-200">Edit</button>
                                <form method="POST" action="{{ route('settings.tanks') }}/{{ $t->id }}/delete" onsubmit="return confirm('Hapus tangki ini?')">
                                    @csrf
                                    <button class="rounded bg-red-100 px-2 py-1 text-xs text-red-700 hover:bg-red-200"><i class="fas fa-trash-can mr-1"></i>Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Belum ada tangki.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 font-semibold">Tambah Tangki</h2>
        <form method="POST" action="{{ route('settings.tanks') }}" class="space-y-3">
            @csrf
            <div>
                <label for="add-tank-name" class="mb-1 block text-sm font-medium">Nama Tangki</label>
                <input type="text" id="add-tank-name" name="tank_name" required placeholder="cth: Bak Atas" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="add-tank-shape" class="mb-1 block text-sm font-medium">Bentuk Tangki</label>
                <select id="add-tank-shape" name="tank_shape" onchange="toggleDimFields('add', this.value)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="kotak">Kotak</option>
                    <option value="bulat">Bulat (Tabung)</option>
                </select>
            </div>
            <div id="add-dim-kotak" class="grid grid-cols-2 gap-2">
                <div>
                    <label for="add-rect-length" class="mb-1 block text-sm font-medium">Panjang (cm)</label>
                    <input type="number" step="0.01" min="0" name="rectangular_length" id="add-rect-length" placeholder="cth: 150" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="add-rect-width" class="mb-1 block text-sm font-medium">Lebar (cm)</label>
                    <input type="number" step="0.01" min="0" name="rectangular_width" id="add-rect-width" placeholder="cth: 120" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div id="add-dim-bulat" class="hidden">
                <div>
                    <label for="add-circ-diameter" class="mb-1 block text-sm font-medium">Diameter (cm)</label>
                    <input type="number" step="0.01" min="0" name="circular_diameter" id="add-circ-diameter" placeholder="cth: 120" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label for="add-tank-height" class="mb-1 block text-sm font-medium">Tinggi Tangki (cm)</label>
                <input type="number" id="add-tank-height" name="height" required min="1" step="0.01" placeholder="cth: 400" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Dipakai perangkat sebagai jarak sensor &rarr; dasar bak (<code>empty_tank_distance</code>). HC-SR04 hanya akurat ±3 m: bila tinggi bak lebih dari itu, level di bawah jangkauan tidak akan pernah terbaca.</p>
            </div>
            <button class="w-full rounded-lg bg-sky-600 py-2 font-semibold text-white hover:bg-sky-700">Simpan</button>
        </form>
    </div>
</div>

{{-- Modal Edit Tangki --}}
<div id="tankModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 font-semibold">Edit Tangki</h2>
        <form id="tankEditForm" method="POST" class="space-y-3">
            @csrf
            <div>
                <label for="tank_name" class="mb-1 block text-sm font-medium">Nama Tangki</label>
                <input type="text" id="tank_name" name="tank_name" required placeholder="cth: Bak Atas" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="tank_shape" class="mb-1 block text-sm font-medium">Bentuk Tangki</label>
                <select id="tank_shape" name="tank_shape" onchange="toggleDimFields('edit', this.value)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="kotak">Kotak</option>
                    <option value="bulat">Bulat (Tabung)</option>
                </select>
            </div>
            <div id="edit-dim-kotak" class="grid grid-cols-2 gap-2">
                <div>
                    <label for="rect_length" class="mb-1 block text-sm font-medium">Panjang (cm)</label>
                    <input type="number" step="0.01" min="0" id="rect_length" name="rectangular_length" placeholder="cth: 150" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="rect_width" class="mb-1 block text-sm font-medium">Lebar (cm)</label>
                    <input type="number" step="0.01" min="0" id="rect_width" name="rectangular_width" placeholder="cth: 120" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div id="edit-dim-bulat" class="hidden">
                <div>
                    <label for="circ_diameter" class="mb-1 block text-sm font-medium">Diameter (cm)</label>
                    <input type="number" step="0.01" min="0" id="circ_diameter" name="circular_diameter" placeholder="cth: 120" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label for="height" class="mb-1 block text-sm font-medium">Tinggi Tangki (cm)</label>
                <input type="number" id="height" name="height" required min="1" step="0.01" placeholder="cth: 400" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Dipakai perangkat sebagai jarak sensor &rarr; dasar bak (<code>empty_tank_distance</code>) sehingga persen level akurat. HC-SR04 hanya akurat ±3 m: di bawah itu sensor melaporkan "tidak terbaca" dan pompa mengisi tanpa ukuran level.</p>
            </div>
            <div class="flex gap-2">
                <button class="w-full rounded-lg bg-sky-600 py-2 font-semibold text-white hover:bg-sky-700">Simpan Perubahan</button>
                <button type="button" onclick="closeModal('tankModal')" class="w-full rounded-lg bg-slate-200 py-2 font-semibold text-slate-700 hover:bg-slate-300">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDimFields(prefix, shape) {
    const isBulat = shape === 'bulat';
    document.getElementById(prefix + '-dim-kotak').classList.toggle('hidden', isBulat);
    document.getElementById(prefix + '-dim-bulat').classList.toggle('hidden', !isBulat);
}
function editTank(id, name, shape, height, rectLen, rectWid, circDia) {
    const f = document.getElementById('tankEditForm');
    f.action = '{{ route('settings.tanks') }}/' + id;
    document.getElementById('tank_name').value = name;
    document.getElementById('tank_shape').value = shape;
    document.getElementById('height').value = height;
    document.getElementById('rect_length').value = rectLen;
    document.getElementById('rect_width').value = rectWid;
    document.getElementById('circ_diameter').value = circDia;
    toggleDimFields('edit', shape);
    openModal('tankModal');
}
function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('flex'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }
</script>
@endsection
