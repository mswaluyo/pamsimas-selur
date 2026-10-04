@extends('layouts.app')
@section('title', 'Kasir Meter Air')

@section('content')
<div class="mb-4 flex flex-wrap gap-2 border-b" id="meter-tabs">
    <button data-tab="queue" class="rounded-t-lg border-b-2 border-sky-600 px-4 py-2 text-sm font-semibold"><i class="fas fa-list-check"></i> Antrean Validasi <span id="badge-pending" class="ml-1 rounded-full bg-sky-600 px-2 py-0.5 text-xs text-white">0</span></button>
    <button data-tab="manual" class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-semibold hover:text-sky-600"><i class="fas fa-keyboard"></i> Input Manual</button>
    <button data-tab="report" class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-semibold hover:text-sky-600"><i class="fas fa-table"></i> Laporan Meter</button>
</div>

<!-- TAB 1: Antrean Validasi -->
<div id="tab-queue" class="rounded-xl bg-white p-5 shadow">
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Foto meteran dari WhatsApp warga (OCR lokal). Klik <strong>Validasi</strong> untuk memeriksa & menyimpan.</p>
        <div class="flex gap-2">
            <button id="btn-bulk" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"><i class="fas fa-check-double"></i> Validasi Massal (OCR)</button>
            <form method="POST" action="{{ route('meter.delete-all-pending') }}" onsubmit="return confirm('Hapus SEMUA antrean?')">
                @csrf
                <button class="rounded-lg bg-red-100 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-200">Bersihkan Semua</button>
            </form>
        </div>
    </div>
    <div id="queue-loading" class="py-8 text-center text-slate-400">Memuat antrean...</div>
    <div id="queue-list" class="hidden grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"></div>
</div>

<!-- TAB 2: Input Manual -->
<div id="tab-manual" class="hidden rounded-xl bg-white p-5 shadow">
    <form method="POST" action="{{ route('meter.store') }}" class="mx-auto max-w-lg space-y-4">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium">Pelanggan</label>
            <select id="manual-customer" name="customer_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">— Pilih pelanggan —</option>
                @foreach($customers as $c)
                <option value="{{ $c->customer_id }}">{{ $c->name }} ({{ $c->customer_id }})</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1 block text-sm font-medium">Periode</label>
                <input type="text" name="period" value="{{ $default_period }}" pattern="\d{4}-\d{2}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Angka Meter Saat Ini</label>
                <input type="number" step="0.01" min="0" name="current_meter" required id="manual-meter" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>
        <div id="manual-info" class="rounded-lg bg-sky-50 p-3 text-sm text-sky-800 hidden"></div>
        <button class="rounded-lg bg-sky-600 px-5 py-2.5 font-semibold text-white hover:bg-sky-700">Simpan Pencatatan</button>
        <p class="text-xs text-slate-500">Tarif saat ini: Rp {{ number_format($water_price, 0, ',', '.') }}/m³ + admin Rp {{ number_format($admin_fee, 0, ',', '.') }}. Sistem menghitung pemakaian dari pembacaan terakhir secara otomatis.</p>
    </form>
</div>

<!-- TAB 3: Laporan -->
<div id="tab-report" class="hidden rounded-xl bg-white p-5 shadow">
    <div class="mb-4 flex flex-wrap gap-2">
        <input type="text" id="report-period" value="{{ $default_period }}" pattern="\d{4}-\d{2}" placeholder="YYYY-MM" class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select id="report-address" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua alamat</option>
            @foreach($unique_addresses as $a)
            <option value="{{ $a }}">{{ $a }}</option>
            @endforeach
        </select>
        <button id="btn-report" class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white">Tampilkan</button>
        <span id="report-summary" class="self-center text-sm"></span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-xs uppercase text-slate-500">
                    <th class="py-2">Nama</th>
                    <th class="py-2">ID</th>
                    <th class="py-2">Alamat</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Angka</th>
                    <th class="py-2">Aksi</th>
                </tr>
            </thead>
            <tbody id="report-body"><tr><td colspan="6" class="py-6 text-center text-slate-400">Pilih periode lalu klik Tampilkan.</td></tr></tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const photoUrlBase = '/meter-photos';

// ---- Tabs ----
const tabs = document.querySelectorAll('#meter-tabs button');
tabs.forEach(btn => btn.addEventListener('click', () => {
    tabs.forEach(b => { b.classList.remove('border-sky-600'); b.classList.add('border-transparent'); });
    btn.classList.add('border-sky-600'); btn.classList.remove('border-transparent');
    ['queue', 'manual', 'report'].forEach(t => document.getElementById('tab-' + t).classList.toggle('hidden', t !== btn.dataset.tab));
    if (btn.dataset.tab === 'queue') loadQueue();
}));

// ---- Tab 1: Antrean ----
async function loadQueue() {
    try {
        const res = await fetch('{{ url("meter/validation-queue") }}');
        const data = await res.json();
        const pending = data.pending || [];
        document.getElementById('badge-pending').textContent = pending.length;
        const list = document.getElementById('queue-list');
        document.getElementById('queue-loading').classList.add('hidden');
        list.classList.remove('hidden');
        list.innerHTML = pending.map(p => `
            <div class="rounded-xl border border-slate-200 p-4" data-session="${p.session_id}">
                <div class="mb-2 flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-sm">${p.name} <span class="font-mono text-xs text-slate-400">${p.customer_id}</span></p>
                        <p class="text-xs text-slate-400">${new Date(p.record_time).toLocaleString('id-ID')}</p>
                    </div>
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">${p.status}</span>
                </div>
                <img src="${photoUrlBase}/${p.foto_path}" onerror="this.style.display='none'" class="mb-3 max-h-40 w-full rounded-lg object-contain bg-slate-50">
                <div class="flex items-center gap-2">
                    <input type="number" step="0.01" min="0" value="${p.current_meter > 0 ? p.current_meter : ''}" placeholder="Angka meter" class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-sm" data-angka>
                    <button onclick="validateOne('${p.session_id}', this)" class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700">Validasi</button>
                    <button onclick="deletePending('${p.session_id}')" class="rounded-lg bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-700"><i class="fas fa-trash"></i></button>
                </div>
            </div>`).join('') || '<p class="col-span-full py-4 text-center text-slate-400">Antrean kosong. <i class="fas fa-check-circle"></i></p>';
    } catch (e) { console.error(e); }
}

window.validateOne = async function (sessionId, btn) {
    const card = btn.closest('[data-session]');
    const angka = card.querySelector('[data-angka]').value;
    if (!angka) return alert('Masukkan angka meter.');
    const res = await fetch('{{ url("meter/admin-validate") }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
        body: JSON.stringify({session_id: sessionId, angka: parseFloat(angka)})
    });
    const data = await res.json();
    alert(data.message || 'Selesai');
    loadQueue();
};

window.deletePending = async function (sessionId) {
    if (!confirm('Hapus antrean ini?')) return;
    const form = new FormData();
    form.append('_token', csrf);
    await fetch('{{ url("meter/delete-pending") }}/' + sessionId, {method: 'POST', body: form});
    loadQueue();
};

document.getElementById('btn-bulk')?.addEventListener('click', async () => {
    const sessions = [...document.querySelectorAll('#queue-list [data-session]')].map(el => el.dataset.session);
    if (!sessions.length) return alert('Antrean kosong.');
    if (!confirm('Validasi massal ' + sessions.length + ' antrean dengan angka OCR?')) return;
    const res = await fetch('{{ url("meter/bulk-validate") }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
        body: JSON.stringify({sessions})
    });
    const data = await res.json();
    alert(`Selesai: ${data.validated} valid, ${data.failed} gagal.`);
    loadQueue();
});

// ---- Tab 2: Manual — info pembacaan terakhir ----
const custSelect = document.getElementById('manual-customer');
async function showLastReading() {
    const info = document.getElementById('manual-info');
    if (!custSelect.value) { info.classList.add('hidden'); return; }
    const res = await fetch('{{ url("meter/last-reading") }}/' + custSelect.value);
    const data = await res.json();
    info.classList.remove('hidden');
    info.innerHTML = data.data
        ? `Pembacaan terakhir: <strong>${data.data.current_meter}</strong> (periode ${data.data.period})`
        : 'Belum ada pembacaan sebelumnya. Pemakaian dihitung dari 0.';
}
custSelect?.addEventListener('change', showLastReading);

// ---- Tab 3: Laporan ----
document.getElementById('btn-report')?.addEventListener('click', async () => {
    const period = document.getElementById('report-period').value;
    const address = document.getElementById('report-address').value;
    const res = await fetch(`{{ url("meter/meter-report") }}?period=${period}&address=${encodeURIComponent(address)}`);
    const data = await res.json();
    const rows = data.data || [];
    const done = rows.filter(r => r.report_status === 'DONE').length;
    const pend = rows.filter(r => r.report_status === 'PENDING').length;
    const none = rows.filter(r => r.report_status === 'NONE').length;
    document.getElementById('report-summary').innerHTML =
        `<span class="text-emerald-600 font-semibold">${done} selesai</span> · <span class="text-amber-600">${pend} pending</span> · <span class="text-red-600">${none} belum</span>`;
    const badges = {DONE: ['bg-emerald-100 text-emerald-700', '<i class="fas fa-check"></i> Selesai'], PENDING: ['bg-amber-100 text-amber-700', '<i class="fas fa-hourglass-half"></i> Sudah Lapor'], NONE: ['bg-red-100 text-red-700', '<i class="fas fa-times"></i> Belum Lapor']};
    document.getElementById('report-body').innerHTML = rows.map(r => `
        <tr class="border-b hover:bg-slate-50">
            <td class="py-2">${r.name}</td>
            <td class="py-2 font-mono text-xs">${r.customer_id}</td>
            <td class="py-2 text-xs text-slate-500">${r.address || '-'}</td>
            <td class="py-2"><span class="rounded-full px-2 py-0.5 text-xs font-semibold ${badges[r.report_status][0]}">${badges[r.report_status][1]}</span></td>
            <td class="py-2 font-semibold">${r.current_meter ?? (r.pending_meter ?? '-')}</td>
            <td class="py-2">${r.phone ? `<button onclick="remind('${r.phone}')" class="rounded bg-emerald-100 px-2 py-1 text-xs text-emerald-700">WhatsApp</button>` : '-'}</td>
        </tr>`).join('');
});

window.remind = function (phone) {
    const message = prompt('Pesan WhatsApp:', 'PAMSIMAS DESA SELUR\nMohon kirim FOTO meteran air Anda. Terima kasih.');
    if (!message) return;
    const form = new FormData();
    form.append('_token', csrf);
    form.append('phone', phone);
    form.append('message', message);
    fetch('{{ url("meter/send-wa") }}', {method: 'POST', body: form}).then(() => alert('Pesan dikirim.'));
};

loadQueue();
</script>
@endpush
