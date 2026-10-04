@extends('layouts.app')
@section('title', 'Dashboard Monitoring')
@section('subtitle', 'Ringkasan kondisi PAMSIMAS Desa Selur')

@section('content')
<!-- Kartu statistik -->
<div class="stat-grid">
    @foreach([
        ['Perangkat Online', $stats['online_devices'] . ' / ' . $stats['total_devices'], 'fa-wifi', 'bg-green'],
        ['Total Tangki', $stats['total_tanks'], 'fa-database', 'bg-orange'],
        ['Tagihan Belum Bayar', $stats['invoices_ready_to_pay'], 'fa-money-bill-wave', 'bg-blue'],
        ['Meter Menunggu Validasi', $stats['meter_pending_validation'], 'fa-file-invoice-dollar', 'bg-purple'],
    ] as $card)
    <div class="group stat-tile-card flex items-center gap-4 rounded-xl bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md" title="{{ $card[0] }}">
        <span class="stat-tile {{ $card[3] }}"><i class="fas {{ $card[2] }}"></i></span>
        <div>
            <p class="stat-card-title text-sm font-medium text-slate-500">{{ $card[0] }}</p>
            <p class="stat-card-value text-3xl font-bold text-slate-900">{{ $card[1] }}</p>
        </div>
    </div>
    @endforeach
</div>
{{-- Gauge Live: 1 kartu putih PER PERANGKAT — ukuran mengikuti sistem lama:
     repeat(auto-fill, minmax(280px, 1fr)) gap 20px, kartu otomatis wrap ke baris berikutnya --}}
<section id="section-gauges" class="mt-5" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;"
         data-thresholds='@json($indicator_settings)' data-template='@json($gaugeTemplate)'>
    <!-- Skeleton loading awal (diganti oleh JS saat data pertama tiba) -->
    <div class="rounded-xl bg-white p-5 shadow-sm">
        <div class="mx-auto mb-3 h-4 w-24 animate-pulse rounded bg-slate-200"></div>
        <div class="mx-auto h-28 w-28 animate-pulse rounded-full bg-slate-200"></div>
        <div class="mx-auto mt-3 h-4 w-32 animate-pulse rounded bg-slate-200"></div>
        <div class="mx-auto mt-2 h-3 w-24 animate-pulse rounded bg-slate-100"></div>
    </div>
    <div class="rounded-xl bg-white p-5 shadow-sm">
        <div class="mx-auto mb-3 h-4 w-24 animate-pulse rounded bg-slate-200"></div>
        <div class="mx-auto h-28 w-28 animate-pulse rounded-full bg-slate-200"></div>
        <div class="mx-auto mt-3 h-4 w-32 animate-pulse rounded bg-slate-200"></div>
        <div class="mx-auto mt-2 h-3 w-24 animate-pulse rounded bg-slate-100"></div>
    </div>
</section>

{{-- Detail perangkat dibuka sebagai HALAMAN (/devices/show/{id}) seperti sistem lama — bukan pop up --}}
@endsection
@push('styles')
<style>
/* ==== Kartu gauge: header indikator + footer aksi (pola sistem lama) ==== */
/* Ukuran kartu mengikuti sistem lama: .gauge-container auto-fill minmax(280px,1fr) */
@media (max-width: 640px) { #section-gauges { gap:10px !important; } }
.gauge-slot { position: relative; }
/* Judul bak di atas gauge — ukuran/gaya mengikuti sistem lama */
.gauge-title { font-weight: 600; color:#2c3e50; margin-bottom:10px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
.gauge-header-container { display:flex; align-items:center; justify-content:space-between; width:100%; height:28px; margin-bottom:6px; }
.online-indicator { width:10px; height:10px; border-radius:9999px; background:#cbd5e1; box-shadow:0 0 0 3px rgba(148,163,184,.22); display:inline-block; flex:none; }
.online-indicator.is-online { background:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,.22); animation:pumpPulse 1.8s ease-in-out infinite; }
.online-indicator.is-offline { background:#94a3b8; }
.signal-indicator { display:inline-flex; align-items:center; gap:4px; font-size:.7rem; color:#64748b; }
.signal-indicator .bars { display:inline-flex; align-items:flex-end; gap:1.5px; height:12px; }
.signal-indicator .bars i { width:3px; border-radius:1px; background:#e2e8f0; }
.signal-indicator .bars i:nth-child(1) { height:4px; }
.signal-indicator .bars i:nth-child(2) { height:7px; }
.signal-indicator .bars i:nth-child(3) { height:10px; }
.signal-indicator .bars i:nth-child(4) { height:12px; }
.signal-indicator .bars i.on { background:#22c55e; }
.signal-indicator.is-weak .bars i.on { background:#f59e0b; }
.pump-info-label { display:flex; align-items:center; justify-content:center; gap:6px; margin-top:8px; font-size:.85rem; font-weight:700; color:#34495e; }
/* Ikon kipas pompa memakai Font Awesome (tema backup) — berputar dgn .fa-spin bawaan FA */
.pump-info-label i[data-fan] { font-size:.72rem; color:#94a3b8; flex:none; }
.pump-info-label i[data-fan].fa-spin { color:#16a34a; }
.gauge-actions { display:flex; justify-content:space-between; align-items:center; gap:8px; width:100%; margin-top:10px; padding:8px 12px; border-top:1px solid #e0e0e0; border-radius:4px; background:#f9f9f9; }
.btn-action { padding:5px 10px; font-size:.8rem; font-weight:700; border-radius:5px; border:1px solid #ccc; background:#fff; color:#34495e; cursor:pointer; transition:all .2s; }
.btn-action:hover:not(:disabled) { background-color:#e2e6ea; }
.btn-action:disabled { cursor:not-allowed; opacity:.6; }
.btn-blue { background-color:#3498db; color:#fff; border-color:#3498db; }
.btn-blue:hover:not(:disabled) { background-color:#2f89c5; }
.btn-gray { background-color:#7f8c8d; color:#fff; border-color:#7f8c8d; }
.btn-gray:hover:not(:disabled) { background-color:#6f7c7d; }
.btn-green { background-color:#27ae60; color:#fff; border-color:#27ae60; }
.btn-green:hover:not(:disabled) { background-color:#229954; }
.btn-red { background-color:#e74c3c; color:#fff; border-color:#e74c3c; }
.btn-red:hover:not(:disabled) { background-color:#d0402f; }
.gauge-mac { margin-top:6px; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:.68rem; color:#94a3b8; }
.pump-duration-badge { font-size:.72rem; font-weight:700; color:#2c3e50; background:#f1f5f9; border-radius:9999px; padding:2px 8px; font-family:monospace; }
.pump-duration-badge.is-on { background:#27ae60; color:#fff; }
.pump-duration-badge.is-off { background:#7f8c8d; color:#fff; }
/* Badge tipe perangkat (MON / ACT) — di header kartu, berdampingan dengan indikator online */
.hdr-left { display:inline-flex; align-items:center; gap:5px; flex:none; }
.device-type-badge { font-size:.62rem; font-weight:700; letter-spacing:.04em; border-radius:4px; padding:1px 5px; border:1px solid transparent; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; flex:none; }
.device-type-badge.is-mon { background:#eef2ff; color:#4338ca; border-color:#c7d2fe; }
.device-type-badge.is-act { background:#fff7ed; color:#c2410c; border-color:#fed7aa; }
/* ==== Kartu statistik: kotak ikon gaya SISTEM LAMA (backup_pamsimas/public/css/style.css:249)
   — lingkaran 50px, ikon 24px putih, warna solid; menggantikan gradien Tailwind ==== */
.stat-tile { width:50px; height:50px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex:none; color:#fff; font-size:24px; transition:transform .2s ease; }
.stat-tile.bg-blue { background:#3498db; }
.stat-tile.bg-green { background:#27ae60; }
.stat-tile.bg-orange { background:#f39c12; }
.stat-tile.bg-red { background:#e74c3c; }
.stat-tile.bg-purple { background:#6f42c1; }
.group:hover .stat-tile { transform:scale(1.05); }
/* ==== Grid kartu statistik: selalu 4 kolom dalam SATU baris (backup_pamsimas dashboard.css) ==== */
.stat-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; }
/* ==== Mobile (<768px): 4 ikon tetap 1 baris — judul disembunyikan, kartu & ikon dirapatkan
      (backup_pamsimas/public/css/responsive.css:45,57-60) ==== */
@media (max-width:767px) {
    .stat-grid { gap:5px; }
    .stat-tile-card { flex-direction:column; gap:4px; padding:8px 4px; text-align:center; align-items:center; }
    .stat-tile-card .stat-tile { width:35px; height:35px; font-size:16px; }
    .stat-tile-card .stat-card-title { display:none; }
    .stat-tile-card .stat-card-value { font-size:.78rem; line-height:1.15; }
}
@keyframes pumpPulse { 0%,100% { opacity:1; } 50% { opacity:.4; } }
@keyframes fanSpin { to { transform:rotate(360deg); } }
</style>
@endpush
@push('scripts')
<script>
@verbatim
const el = document.getElementById('section-gauges');
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
const gaugeArea = document.getElementById('section-gauges');
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
    // Catatan: render per-slot dilakukan di ensureSlots() saat data perangkat datang
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

// --- Render gauge: 1 slot per perangkat ---
// Gauge fallback (tanpa template) berupa tangki SVG dengan id unik per slot.
function fallbackSvg(i) {
    return `
    <svg viewBox="0 0 120 200" class="h-64 w-40">
        <defs>
            <linearGradient id="wg${i}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#38bdf8"/>
                <stop offset="100%" stop-color="#0284c7"/>
            </linearGradient>
            <clipPath id="tc${i}"><rect x="19" y="11" width="82" height="178" rx="7"/></clipPath>
        </defs>
        <rect x="18" y="10" width="84" height="180" rx="8" fill="#e2e8f0" stroke="#94a3b8" stroke-width="2"/>
        <rect data-water="${i}" x="19" y="189" width="82" height="0" fill="url(#wg${i})" clip-path="url(#tc${i})" style="transition: all 1s ease"/>
        <text data-pct="${i}" x="60" y="100" text-anchor="middle" font-size="20" font-weight="bold" fill="#0f172a">--%</text>
        <text x="60" y="122" text-anchor="middle" font-size="9" fill="#64748b">level air</text>
    </svg>`;
}

let gaugeSlots = [];
let gaugeSig = '';

// --- Badge tipe perangkat: MON = MONITOR (sensor + pompa), ACT = ACTUATOR (pompa saja) ---
function deviceTypeInfo(type) {
    const t = String(type || '').toUpperCase();
    if (t === 'MONITOR') return { label: 'MON', cls: 'is-mon', title: 'Tipe perangkat: MONITOR — sensor + pompa (fungsi ganda)' };
    if (t === 'ACTUATOR') return { label: 'ACT', cls: 'is-act', title: 'Tipe perangkat: ACTUATOR — pompa saja (tanpa baca sensor)' };
    return null;
}
function deviceTypeBadgeHtml(type) {
    const info = deviceTypeInfo(type);
    if (!info) return '';
    return '<span class="device-type-badge ' + info.cls + '" data-device-type title="' + info.title + '">' + info.label + '</span>';
}

// --- Header kartu gauge: tipe perangkat + indikator online/offline + kekuatan sinyal ---
function cardHeader(d) {
    return `<div class="gauge-header-container">
        <span class="hdr-left">
            <span class="online-indicator is-offline" data-online-ind title="Status perangkat"></span>${deviceTypeBadgeHtml(d.device_type)}
        </span>
        <span class="signal-indicator" data-signal title="Kekuatan sinyal WiFi perangkat">
            <span class="bars"><i></i><i></i><i></i><i></i></span>
            <span data-signal-db>—</span>
        </span>
    </div>`;
}

// Kekuatan sinyal: 4 bar (>= -60 dBm), 3 (-70), 2 (-80), 1 (< -80), 0 = offline
function signalLevel(rssi, online) {
    if (!online) return 0;
    const v = Number(rssi) || 0;
    if (v >= -60) return 4;
    if (v >= -70) return 3;
    if (v >= -80) return 2;
    return 1;
}

// Kirim perintah kontrol ke endpoint yang sama dipakai firmware (set_mode / set_pump)
async function sendCommand(mac, action, value) {
    if (!mac) return;
    try {
        const res = await fetch('/api/device-command', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ mac: mac, action: action, value: value }),
        });
        const out = await res.json().catch(() => ({}));
        if (!res.ok || out.status === 'error') {
            window.alert(out.message || 'Perintah gagal dikirim.');
            return;
        }
        refresh();
    } catch (e) {
        console.error(e);
        window.alert('Gagal menghubungi server.');
    }
}

// --- Badge timer durasi pompa: hijau = lama nyala (ON), abu = lama mati (OFF) — count-up, bukan countdown ---
const pumpTimers = {};
function pumpTimerState(id) {
    return pumpTimers[id] || (pumpTimers[id] = { on: null, off: null, last: null });
}
function pad2(n) { return String(n).padStart(2, '0'); }
function fmtClock(totalSec) {
    totalSec = Math.max(0, Math.floor(Number(totalSec) || 0));
    return pad2(Math.floor(totalSec / 3600)) + ':' + pad2(Math.floor((totalSec % 3600) / 60)) + ':' + pad2(totalSec % 60);
}
function renderPumpTimer(b) {
    b.textContent = b._sinceMs ? fmtClock((Date.now() - b._sinceMs) / 1000) : '--:--:--';
    b.classList.toggle('is-on', !!b._isOn);
    b.classList.toggle('is-off', !b._isOn);
}
setInterval(function () {
    document.querySelectorAll('[data-pump-timer]').forEach(renderPumpTimer);
}, 1000);

function ensureSlots(devices) {
    // Bangun ulang kartu bila jumlah ATAU komposisi perangkat berubah
    const sig = devices.map(d => d.id + ':' + d.mac_address).join('|');
    if (sig === gaugeSig && gaugeSlots.length === devices.length) return;
    gaugeSig = sig;
    gaugeArea.innerHTML = '';
    gaugeSlots = [];
    devices.forEach((d, i) => {
        const slot = document.createElement('div');
        slot.className = 'gauge-slot rounded-lg bg-white p-5 shadow-sm flex flex-col items-center cursor-pointer transition hover:shadow-md';
        // Klik kartu → halaman detail perangkat (sama seperti sistem lama)
        slot.dataset.detailUrl = '/devices/show/' + d.id;
        let html;
        if (gaugeTemplated && gaugeTpl && gaugeTpl.html_code) {
            html = gaugeTpl.html_code
                // Judul di atas gauge = identitas BAK (tangki)
                .replace(/{{\s*TANK[ _-]*NAME\s*}}/gi, d.tank_name || '-')
                .replace(/{{\s*DEVICE[ _-]*ID\s*}}/gi, String(d.id))
                .replace(/{{\s*PUMP[ _-]*NAME\s*}}/gi, d.pump_name || 'Pompa');
            // Slot > 0: prefixkan semua id agar tidak bentrok antar slot
            if (i > 0) html = html.replace(/\bid="/g, 'id="g' + i + '-');
        } else {
            // Tanpa template: tetap tampilkan identitas bak di atas gauge bawaan
            html = `<div class="gauge-title">${d.tank_name || '-'}</div>` + fallbackSvg(i);
        }
        html += `<div class="pump-info-label" data-pump-label><i class="fas fa-fan" data-fan></i><span data-pump-label-text>${d.pump_name || 'Tanpa Pompa'}</span></div>`
        // Indikator online + sinyal di atas, tombol mode & pompa di bawah, MAC paling bawah.
        html = cardHeader(d) + html
            + `<div class="gauge-actions">`
            +   `<button type="button" class="btn-action btn-gray" data-mode-toggle data-mac="${d.mac_address}" title="Mode kontrol (AUTO/MANUAL)">—</button>`
            +   `<button type="button" class="btn-action btn-gray" data-pump-toggle data-mac="${d.mac_address}" title="Kontrol pompa (manual)">—</button>`
            + `</div>`
            + `<p class="gauge-mac">${d.mac_address}</p>`;
        slot.innerHTML = html;
        gaugeArea.appendChild(slot);
        gaugeSlots.push(slot);
        if (gaugeTemplated && typeof window.initGauge === 'function') {
            try { window.initGauge(slot); } catch (e) { console.error(e); }
        }
    });
}

function updateSlot(slot, idx, dev, pct, color) {
    // --- Header: indikator online/offline + kekuatan sinyal ---
    const dot = slot.querySelector('[data-online-ind]');
    if (dot) {
        dot.className = 'online-indicator ' + (dev.is_online ? 'is-online' : 'is-offline');
        dot.title = (dev.is_online ? 'Perangkat online' : 'Perangkat offline')
            + (dev.last_update_ts ? ' — update terakhir ' + fmtTime(dev.last_update_ts) : '');
    }

    // --- Badge tipe perangkat (MON / ACT) — mengikuti data terbaru dari API ---
    const typeBadge = slot.querySelector('[data-device-type]');
    if (typeBadge) {
        const info = deviceTypeInfo(dev.device_type);
        typeBadge.textContent = info ? info.label : '';
        typeBadge.title = info ? info.title : '';
        typeBadge.className = 'device-type-badge' + (info ? ' ' + info.cls : '');
        typeBadge.style.display = info ? '' : 'none';
    }
    const sig = slot.querySelector('[data-signal]');
    if (sig) {
        const lvl = signalLevel(dev.rssi, dev.is_online);
        sig.querySelectorAll('.bars i').forEach((b, bi) => b.classList.toggle('on', bi < lvl));
        sig.classList.toggle('is-weak', lvl > 0 && lvl <= 2);
        const db = sig.querySelector('[data-signal-db]');
        if (db) db.textContent = dev.is_online ? ((dev.rssi ?? 0) + ' dBm') : 'offline';
    }

    // --- Label nama pompa + kipas berputar saat pompa nyala ---
    const pumpLabel = slot.querySelector('[data-pump-label]');
    if (pumpLabel) {
        const txt = pumpLabel.querySelector('[data-pump-label-text]');
        if (txt) txt.textContent = dev.pump_name || 'Tanpa Pompa';
        const fan = pumpLabel.querySelector('[data-fan]');
        if (fan) fan.classList.toggle('fa-spin', dev.status === 'ON' && dev.is_online);
        pumpLabel.style.color = dev.is_online ? '#34495e' : '#94a3b8';
    }
    // --- Footer: tombol mode (AUTO/MANUAL) & tombol pompa (ON/OFF) ---
    const mode = (dev.control_mode || 'AUTO').toUpperCase();
    const mBtn = slot.querySelector('[data-mode-toggle]');
    if (mBtn) {
        mBtn.dataset.mode = mode;
        mBtn.textContent = mode;
        mBtn.className = 'btn-action ' + (mode === 'AUTO' ? 'btn-blue' : 'btn-gray');
        mBtn.disabled = !dev.is_online;
        mBtn.title = 'Mode kontrol sekarang: ' + mode + ' — klik untuk mengubah';
    }
    const pBtn = slot.querySelector('[data-pump-toggle]');
    if (pBtn) {
        const on = dev.status === 'ON';
        pBtn.dataset.status = on ? 'ON' : 'OFF';
        pBtn.textContent = on ? 'ON' : 'OFF';
        // Warna mengikuti sistem lama: abu (offline) / hijau (ON) / merah (OFF)
        pBtn.className = 'btn-action ' + (!dev.is_online ? 'btn-gray' : (on ? 'btn-green' : 'btn-red'));
        pBtn.disabled = !dev.is_online || mode !== 'MANUAL';
        pBtn.title = !dev.is_online
            ? 'Perangkat offline — perintah tidak dapat dikirim'
            : (mode !== 'MANUAL'
                ? 'Mode AUTO: pompa dikendalikan otomatis. Ubah ke MANUAL untuk kontrol manual.'
                : 'Pompa sekarang ' + (on ? 'ON' : 'OFF') + ' — klik untuk ' + (on ? 'mematikan' : 'menyalakan'));
    }

    // --- Badge timer durasi: hijau = lama nyala, abu = lama mati (count-up) ---
    let tBadge = slot.querySelector('[data-pump-timer]');
    if (!tBadge) {
        tBadge = document.createElement('span');
        tBadge.setAttribute('data-pump-timer', '');
        tBadge.className = 'pump-duration-badge';
        const hdr = slot.querySelector('.gauge-header-container');
        const sigEl = hdr ? hdr.querySelector('.signal-indicator') : null;
        if (hdr) {
            // Posisi: di antara indikator online dan sinyal (seperti halaman detail)
            if (sigEl) hdr.insertBefore(tBadge, sigEl); else hdr.appendChild(tBadge);
        } else {
            const act = slot.querySelector('.gauge-actions');
            if (act) act.insertBefore(tBadge, act.querySelector('[data-pump-toggle]') || null);
        }
    }
    if (tBadge) {
        const online = !!dev.is_online;
        // Saat device offline: status terakhir tidak berlaku → pompa dianggap OFF,
        // timer dihitung sejak kontak terakhir (last_update_ts), bukan tetap ON.
        const onP = online && dev.status === 'ON';
        const sinceTs = online
            ? (Number(dev.pump_status_since) || 0)
            : (Number(dev.last_update_ts) || 0);
        const st = pumpTimerState(dev.id);
        if (sinceTs > 0) {
            if (onP) st.on = sinceTs * 1000; else st.off = sinceTs * 1000;
        } else {
            if (onP && (st.on === null || st.last === 'OFF')) st.on = Date.now();
            if (!onP && (st.off === null || st.last === 'ON')) st.off = Date.now();
        }
        st.last = onP ? 'ON' : 'OFF';
        tBadge._sinceMs = (onP ? st.on : st.off) || null;
        tBadge._isOn = onP;
        tBadge.title = !online
            ? 'Perangkat offline — pompa dianggap mati; timer dihitung sejak kontak terakhir'
            : (onP ? 'Lama waktu pompa nyala (timer berjalan)' : 'Lama waktu pompa mati (timer berjalan)');
        renderPumpTimer(tBadge);
    }

    // --- Gauge ---
    if (gaugeTemplated) {
        try { window.updateGauge(slot, pct, color); } catch (e) { console.error(e); }
        return;
    }
    const water = slot.querySelector(`[data-water="${idx}"]`);
    if (water) {
        const h = pct / 100 * 178;
        water.setAttribute('y', 189 - h);
        water.setAttribute('height', h + 1);
    }
    const pctEl = slot.querySelector(`[data-pct="${idx}"]`);
    if (pctEl) pctEl.textContent = pct.toFixed(0) + '%';
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
        if (res.status === 401) { window.location.href = '/login'; return; } // sesi berakhir → login ulang
        const data = await res.json();
        const devices = data.devices || [];
        devCache = devices;

        // Tabel status perangkat (dinonaktifkan — info penuh ada di kartu gauge & modal detail)
        const dt = document.getElementById('device-table');
        if (dt) dt.innerHTML = renderDevices(devices);

        // Gauge: 1 per perangkat
        if (devices.length) {
            ensureSlots(devices);
            devices.forEach((d, i) => {
                const pct = Math.max(0, Math.min(100, d.water_percentage ?? 0));
                const color = pct > thMid ? cHigh : (pct > thLow ? cMid : cLow);
                const slot = gaugeSlots[i];
                updateSlot(slot, i, d, pct, color);
            });
        } else {
            gaugeArea.innerHTML = '<p class="w-full text-center text-sm text-slate-400">Belum ada perangkat terdaftar.</p>';
        }
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

// --- Klik kartu gauge → buka HALAMAN detail perangkat (pola sistem lama) ---
gaugeArea.addEventListener('click', (e) => {
    // Tombol aksi di dalam kartu: kirim perintah, jangan navigasi
    const mBtn = e.target.closest('[data-mode-toggle]');
    if (mBtn && !mBtn.disabled && mBtn.dataset.mac) {
        const cur = (mBtn.dataset.mode || 'AUTO').toUpperCase();
        sendCommand(mBtn.dataset.mac, 'set_mode', cur === 'MANUAL' ? 'AUTO' : 'MANUAL');
        return;
    }
    const pBtn = e.target.closest('[data-pump-toggle]');
    if (pBtn) {
        if (pBtn.disabled) return; // mode AUTO / offline → tombol tidak berfungsi
        if (pBtn.dataset.mac) {
            const next = (pBtn.dataset.status || 'OFF').toUpperCase() === 'ON' ? 'OFF' : 'ON';
            sendCommand(pBtn.dataset.mac, 'set_pump', next);
        }
        return;
    }
    // Klik area kartu → halaman detail perangkat
    const slot = e.target.closest('.gauge-slot');
    if (slot && slot.dataset.detailUrl) window.location.href = slot.dataset.detailUrl;
});

refresh();
setInterval(refresh, 5000);
@endverbatim
</script>
@endpush
