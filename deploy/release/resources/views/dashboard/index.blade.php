@extends('layouts.app')
@section('title', 'Dashboard Monitoring')

@section('content')
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['Perangkat Online', $stats['online_devices'] . ' / ' . $stats['total_devices'], '📡', 'bg-sky-500'],
        ['Total Tangki', $stats['total_tanks'], '🛢️', 'bg-violet-500'],
        ['Tagihan Belum Bayar', $stats['invoices_ready_to_pay'], '💰', 'bg-amber-500'],
        ['Meter Menunggu Validasi', $stats['meter_pending_validation'], '🔍', 'bg-emerald-500'],
    ] as $card)
    <div class="flex items-center gap-4 rounded-xl bg-white p-5 shadow">
        <span class="flex h-12 w-12 items-center justify-center rounded-xl {{ $card[3] }} text-2xl">{{ $card[2] }}</span>
        <div>
            <p class="text-sm text-slate-500">{{ $card[0] }}</p>
            <p class="text-2xl font-bold">{{ $card[1] }}</p>
        </div>
    </div>
    @endforeach
</div>

<div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-3">
    <!-- Gauge Live -->
    <div class="rounded-xl bg-white p-5 shadow" id="dashboard-live" data-thresholds='@json($indicator_settings)'>
        <h2 class="mb-4 font-semibold">Level Tandon</h2>
        <div id="gauge-area" class="flex flex-col items-center">
            <svg viewBox="0 0 120 200" class="h-64 w-40">
                <rect x="20" y="10" width="80" height="180" rx="8" fill="#e2e8f0" stroke="#94a3b8" stroke-width="2"/>
                <clipPath id="tank-clip"><rect x="21" y="11" width="78" height="178" rx="7"/></clipPath>
                <rect id="gauge-water" x="21" y="110" width="78" height="179" fill="#0ea5e9" clip-path="url(#tank-clip)" style="transition: all 1s ease"/>
                <text id="gauge-pct" x="60" y="100" text-anchor="middle" font-size="20" font-weight="bold" fill="#0f172a">--%</text>
            </svg>
            <p id="gauge-tank" class="mt-2 text-sm text-slate-500">Menunggu data...</p>
        </div>
    </div>

    <!-- Status Perangkat -->
    <div class="rounded-xl bg-white p-5 shadow xl:col-span-2">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-semibold">Status Perangkat IoT</h2>
            <span class="flex items-center gap-2 text-xs text-slate-400">
                <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span> Live (5 detik)
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-xs uppercase text-slate-500">
                        <th class="py-2">Perangkat</th>
                        <th class="py-2">Tangki</th>
                        <th class="py-2">Level</th>
                        <th class="py-2">Pompa</th>
                        <th class="py-2">Mode</th>
                        <th class="py-2">Status</th>
                    </tr>
                </thead>
                <tbody id="device-table">
                    <tr><td colspan="6" class="py-4 text-center text-slate-400">Memuat...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const el = document.getElementById('dashboard-live');
const thresholds = JSON.parse(el.dataset.thresholds || '{}');
const thLow = parseFloat(thresholds.threshold_low ?? 30);
const thMid = parseFloat(thresholds.threshold_medium ?? 70);
const cLow = thresholds.color_low || '#e74c3c';
const cMid = thresholds.color_medium || '#f39c12';
const cHigh = thresholds.color_high || '#27ae60';

function fmtTime(ts) {
    if (!ts) return '-';
    return new Date(ts * 1000).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
}

async function refresh() {
    try {
        const res = await fetch('/api/dashboard-data');
        const data = await res.json();
        const devices = data.devices || [];
        const first = devices_find(devices);
        if (first) {
            const pct = first.water_percentage ?? 0;
            const color = pct > thMid ? cHigh : (pct > thLow ? cMid : cLow);
            const water = document.getElementById('gauge-water');
            const h = Math.max(0, Math.min(100, pct)) / 100 * 178;
            water.setAttribute('y', 189 - h);
            water.setAttribute('height', h + 1);
            water.setAttribute('fill', color);
            const pctEl = document.getElementById('gauge-pct');
            pctEl.textContent = pct.toFixed(0) + '%';
            pctEl.setAttribute('fill', color);
            document.getElementById('gauge-tank').textContent =
                `${first.tank_name || '-'} — ${first.is_online ? 'Online' : 'Offline'}`;
        }
        const tbody = document.getElementById('device-table');
        tbody.innerHTML = renderDevices(data.devices || []);
    } catch (e) { console.error(e); }
}

function devices_find(list) {
    return list.find(d => d.is_online) || list[0];
}

function renderDevices(list) {
    if (!list.length) return '<tr><td colspan="6" class="py-4 text-center text-slate-400">Belum ada perangkat</td></tr>';
    return list.map(d => `
        <tr class="border-b hover:bg-slate-50">
            <td class="py-2 font-mono text-xs">${d.mac_address}</td>
            <td class="py-2">${d.tank_name || '-'}</td>
            <td class="py-2 font-semibold">${(d.water_percentage ?? 0).toFixed(0)}%</td>
            <td class="py-2"><span class="rounded-full px-2 py-0.5 text-xs font-semibold ${d.status === 'ON' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}">${d.status}</span></td>
            <td class="py-2 text-xs">${d.control_mode}</td>
            <td class="py-2"><span class="flex items-center gap-1.5 text-xs"><span class="h-2 w-2 rounded-full ${d.is_online ? 'bg-emerald-500' : 'bg-red-500'}"></span>${d.is_online ? fmtTime(d.last_update_ts) : 'Offline'}</span></td>
        </tr>`).join('');
}

refresh();
setInterval(refresh, 5000);
</script>
@endpush
