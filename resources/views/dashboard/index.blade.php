@extends('layouts.app')
@section('title', 'Dashboard Monitoring')
@section('subtitle', 'Ringkasan kondisi PAMSIMAS Desa Selur')

@section('content')
<!-- Kartu statistik -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['Perangkat Online', $stats['online_devices'] . ' / ' . $stats['total_devices'], '📡', 'from-sky-500 to-cyan-600'],
        ['Total Tangki', $stats['total_tanks'], '🛢️', 'from-violet-500 to-purple-600'],
        ['Tagihan Belum Bayar', $stats['invoices_ready_to_pay'], '💰', 'from-amber-500 to-orange-600'],
        ['Meter Menunggu Validasi', $stats['meter_pending_validation'], '🔍', 'from-emerald-500 to-teal-600'],
    ] as $card)
    <div class="group flex items-center gap-4 rounded-xl bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br {{ $card[3] }} text-2xl shadow transition group-hover:scale-105">{{ $card[2] }}</span>
        <div>
            <p class="text-sm font-medium text-slate-500">{{ $card[0] }}</p>
            <p class="text-3xl font-bold text-slate-900">{{ $card[1] }}</p>
        </div>
    </div>
    @endforeach
</div>
<div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-3">
    <!-- Gauge Live -->
    <div class="rounded-xl bg-white p-5 shadow-sm" id="dashboard-live" data-thresholds='@json($indicator_settings)' data-template='@json($gaugeTemplate)'>
        <h2 class="mb-4 font-semibold">Level Tandon</h2>
        <div id="gauge-area" class="flex flex-col items-center">
            <svg viewBox="0 0 120 200" class="h-64 w-40">
                <defs>
                    <linearGradient id="water-grad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#38bdf8"/>
                        <stop offset="100%" stop-color="#0284c7"/>
                    </linearGradient>
                </defs>
                <rect x="18" y="10" width="84" height="180" rx="8" fill="#e2e8f0" stroke="#94a3b8" stroke-width="2"/>
                <clipPath id="tank-clip"><rect x="19" y="11" width="82" height="178" rx="7"/></clipPath>
                <rect id="gauge-water" x="19" y="110" width="82" height="179" fill="url(#water-grad)" clip-path="url(#tank-clip)" style="transition: all 1s ease"/>
                <text id="gauge-pct" x="60" y="100" text-anchor="middle" font-size="20" font-weight="bold" fill="#0f172a">--%</text>
                <text x="60" y="122" text-anchor="middle" font-size="9" fill="#64748b">level air</text>
            </svg>
            <p id="gauge-tank" class="mt-2 text-sm text-slate-500">Menunggu data...</p>
        </div>
    </div>

    <!-- Status Perangkat -->
    <div class="rounded-xl bg-white p-5 shadow-sm xl:col-span-2">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-semibold">Status Perangkat IoT</h2>
            <span class="flex items-center gap-2 text-xs text-slate-400">
                <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span> Live (5 detik)
            </span>
        </div>
        <div class="overflow-x-auto rounded-lg border border-slate-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                        <th class="px-3 py-2.5">Perangkat</th>
                        <th class="px-3 py-2.5">Tangki</th>
                        <th class="px-3 py-2.5">Level</th>
                        <th class="px-3 py-2.5">Pompa</th>
                        <th class="px-3 py-2.5">Mode</th>
                        <th class="px-3 py-2.5">Status</th>
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
@verbatim
const el = document.getElementById('dashboard-live');
const thresholds = JSON.parse(el.dataset.thresholds || '{}');
const thLow = parseFloat(thresholds.threshold_low ?? 30);
const thMid = parseFloat(thresholds.threshold_medium ?? 70);
const cLow = thresholds.color_low || '#ef4444';
const cMid = thresholds.color_medium || '#f59e0b';
const cHigh = thresholds.color_high || '#10b981';

// Template gauge aktif (dari menu Template Gauge)
const gaugeTpl = (() => {
    try { return JSON.parse(el.dataset.template || 'null'); } catch (e) { return null; }
})();
const gaugeArea = document.getElementById('gauge-area');
let gaugeTemplated = false;

if (gaugeTpl && gaugeTpl.html_code) {
    gaugeTemplated = true;
    if (gaugeTpl.css_code) {
        const st = document.createElement('style');
        st.id = 'gauge-template-css';
        st.textContent = gaugeTpl.css_code;
        document.head.appendChild(st);
    }
    if (gaugeTpl.js_code) {
        try { (new Function(gaugeTpl.js_code))(); } catch (e) { console.error('Gauge JS error:', e); }
    }
    gaugeArea.innerHTML = gaugeTpl.html_code
        .replace(/{{\s*TANK[ _-]*NAME\s*}}/gi, 'PAMSIMAS')
        .replace(/{{\s*DEVICE[ _-]*ID\s*}}/gi, '0')
        .replace(/{{\s*PUMP[ _-]*NAME\s*}}/gi, 'Pompa');
    if (typeof window.initGauge === 'function') {
        try { window.initGauge(gaugeArea); } catch (e) { console.error(e); }
    }
}

// Fallback universal updateGauge (dipakai jika template tidak mendefinisikannya)
if (typeof window.updateGauge !== 'function') {
    window.updateGauge = function (cardElement, waterLevel, fillColor) {
        cardElement.querySelectorAll('[data-update-style]').forEach(el => {
            const styleProp = el.dataset.updateStyle;
            if (styleProp === 'degrees') {
                el.style.setProperty('--percentage', (waterLevel * 2.7) + 'deg');
                el.style.setProperty('--fill-color', fillColor);
            } else if (styleProp === 'percentage') {
                if (el.classList.contains('tank-gauge-water')) el.style.height = waterLevel + '%';
                else el.style.width = waterLevel + '%';
                el.style.backgroundColor = fillColor;
            }
        });
        const textElement = cardElement.querySelector('.value')
            || cardElement.querySelector('.tank-gauge-text')
            || cardElement.querySelector('.simple-bar-gauge-text');
        if (textElement) textElement.textContent = Math.round(waterLevel);
    };
}

function fmtTime(ts) {
    if (!ts) return '-';
    return new Date(ts * 1000).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
}

// Progress bar level dalam tabel
function levelBar(pct) {
    const p = Math.max(0, Math.min(100, pct ?? 0));
    const color = p > thMid ? cHigh : (p > thLow ? cMid : cLow);
    return `<div class="flex items-center gap-2">
        <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-200"><div style="width:${p}%;background:${color}" class="h-full rounded-full"></div></div>
        <span class="font-semibold">${p.toFixed(0)}%</span>
    </div>`;
}

async function refresh() {
    try {
        const res = await fetch('/api/dashboard-data');
        const data = await res.json();
        const devices = data.devices || [];
        const first = devices.find(d => d.is_online) || devices[0];
        if (first) {
            const pct = first.water_percentage ?? 0;
            const color = pct > thMid ? cHigh : (pct > thLow ? cMid : cLow);
            if (gaugeTemplated) {
                try { window.updateGauge(gaugeArea, Math.max(0, Math.min(100, pct)), color); } catch (e) { console.error(e); }
            } else {
                const water = document.getElementById('gauge-water');
                const h = Math.max(0, Math.min(100, pct)) / 100 * 178;
                water.setAttribute('y', 189 - h);
                water.setAttribute('height', h + 1);
                const pctEl = document.getElementById('gauge-pct');
                pctEl.textContent = pct.toFixed(0) + '%';
            }
            document.getElementById('gauge-tank').textContent =
                `${first.tank_name || '-'} — ${first.is_online ? 'Online' : 'Offline'}`;
        }
        document.getElementById('device-table').innerHTML = renderDevices(devices);
    } catch (e) { console.error(e); }
}

function renderDevices(list) {
    if (!list.length) return '<tr><td colspan="6" class="py-4 text-center text-slate-400">Belum ada perangkat</td></tr>';
    return list.map(d => `
        <tr class="border-b transition hover:bg-slate-50">
            <td class="px-3 py-2.5 font-mono text-xs">${d.mac_address}</td>
            <td class="px-3 py-2.5">${d.tank_name || '-'}</td>
            <td class="px-3 py-2.5">${levelBar(d.water_percentage ?? 0)}</td>
            <td class="px-3 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-semibold ${d.status === 'ON' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}">${d.status}</span></td>
            <td class="px-3 py-2.5 text-xs">${d.control_mode}</td>
            <td class="px-3 py-2.5"><span class="flex items-center gap-1.5 text-xs"><span class="h-2 w-2 rounded-full ${d.is_online ? 'bg-emerald-500' : 'bg-red-500'}"></span>${d.is_online ? fmtTime(d.last_update_ts) : 'Offline'}</span></td>
        </tr>`).join('');
}

refresh();
setInterval(refresh, 5000);
@endverbatim
</script>
@endpush