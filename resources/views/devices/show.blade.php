@extends('layouts.app')
@section('title', 'Detail Perangkat')

@push('styles')
<style>
/* ============================================================================
   Halaman Detail Perangkat — tampilan disamakan dengan SISTEM LAMA (backup):
   backup_pamsimas/app/Views/devices/show.php + web/public/css/devices.css
   ========================================================================== */
#device-show-page { max-width: 1400px; margin: 0 auto; }
#device-show-page .page-header { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
#device-show-page .page-header h1 { font-size:1.35rem; font-weight:800; color:#2c3e50; }

/* --- Statistik utama: SATU baris penuh (tanpa wrap; scroll horizontal hanya bila
       layar sangat sempit) — mengikuti 7 kartu pada sistem lama --- */
#device-show-page .stat-cards-container {
    display:grid;
    grid-auto-flow:column;
    grid-auto-columns:minmax(74px,1fr);
    gap:8px;
    margin-bottom:20px;
    overflow-x:auto;
}
@media (max-width:480px) { #device-show-page .stat-cards-container { padding-bottom:6px; } }
#device-show-page .stat-card { display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; gap:4px; min-width:0; background:#fff; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,.05); padding:10px 6px; }
#device-show-page .stat-card > div { min-width:0; max-width:100%; }
#device-show-page .stat-card-icon { width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.95rem; color:#fff; flex:none; }
#device-show-page .stat-card-title { font-size:.62rem; color:#7f8c8d; text-transform:uppercase; letter-spacing:.02em; line-height:1.15; max-width:100%; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
#device-show-page .stat-card-value { font-size:.9rem; font-weight:800; color:#2c3e50; white-space:nowrap; }
#device-show-page .bg-gray { background:#7f8c8d; } #device-show-page .bg-blue { background:#3498db; }
#device-show-page .bg-orange { background:#f39c12; } #device-show-page .bg-green { background:#27ae60; }
#device-show-page .bg-red { background:#e74c3c; }

/* --- Grid gauge + konfigurasi (350px + 1fr di layar besar) --- */
#device-show-page .controller-detail-grid { display:grid; grid-template-columns:1fr; gap:20px; margin-bottom:20px; }
#device-show-page #gauge-container { background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,.05); display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:450px; }
#device-show-page .gauge-card { width:100%; max-width:320px; background:transparent; border:none; box-shadow:none; }
#device-show-page #gauge-container .info-block { width:100%; max-width:320px; margin:20px 0 0; }

/* --- Kartu umum --- */
#device-show-page .card { background:#fff; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,.05); padding:20px; }
#device-show-page .card + .card { margin-top:20px; }
#device-show-page .card h2 { font-size:1.05rem; font-weight:700; color:#2c3e50; margin-bottom:12px; }

/* --- Detail Konfigurasi --- */
#device-show-page .info-block { margin-bottom:16px; }
#device-show-page .info-block:last-child { margin-bottom:0; }
#device-show-page .info-block-title { display:block; font-weight:700; color:#3498db; font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px; }
#device-show-page .detail-list { list-style:none; padding:0; margin:0; font-size:.9rem; }
#device-show-page .detail-list li { display:flex; justify-content:space-between; gap:10px; padding:9px 0; border-bottom:1px solid #f0f0f0; }
#device-show-page .detail-list .k { color:#7f8c8d; }
#device-show-page .detail-list .v { font-weight:700; color:#2c3e50; text-align:right; }

/* --- Tombol --- */
#device-show-page .btn { display:inline-flex; align-items:center; gap:6px; border:1px solid transparent; border-radius:6px; padding:8px 14px; font-size:.85rem; font-weight:600; cursor:pointer; text-decoration:none; transition:all .2s; }
#device-show-page .btn-secondary { background:#ecf0f1; border-color:#dcdfe0; color:#2c3e50; }
#device-show-page .btn-secondary:hover { background:#e2e6e7; }
#device-show-page .btn-sm { padding:5px 10px; font-size:.75rem; }

/* --- Grafik --- */
#device-show-page .chart-card-container { display:grid; grid-template-columns:1fr auto; align-items:center; gap:10px; }
#device-show-page .chart-title { font-size:1.05rem; font-weight:700; color:#2c3e50; margin:0; }
#device-show-page .chart-controls-container { grid-column:2 / 3; grid-row:1; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
#device-show-page .chart-controls-container .btn-group { display:flex; gap:6px; }
#device-show-page .chart-btn.active { background:#3498db; border-color:#3498db; color:#fff; }
#device-show-page .auto-scale-wrapper { display:flex; align-items:center; gap:6px; }
#device-show-page .auto-scale-wrapper label { font-size:.85rem; cursor:pointer; margin:0; color:#34495e; }
#device-show-page .auto-scale-wrapper input { accent-color:#3498db; }
#device-show-page .chart-canvas-container { grid-column:1 / 3; width:100%; height:300px; position:relative; border-top:1px solid #eee; padding-top:15px; }

/* --- Log Kejadian Terakhir: satu log = satu baris (mudah dibandingkan) --- */
#device-show-page .log-list { list-style:none; padding:0; margin:0; max-height:400px; overflow-y:auto; border:1px solid #e0e0e0; border-radius:8px; background:#fff; }
#device-show-page .log-item { padding:6px 12px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; gap:10px; font-size:.8rem; line-height:1.5; white-space:nowrap; }
#device-show-page .log-item:last-child { border-bottom:none; }
#device-show-page .log-item:hover { background:#f8fafc; }
#device-show-page .log-icon-wrapper { width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:#f1f5f9; color:#64748b; flex:none; font-size:.72rem; }
#device-show-page .log-time { flex:0 0 128px; color:#64748b; font-size:.74rem; font-variant-numeric:tabular-nums; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; }
#device-show-page .log-type { flex:0 0 84px; color:#94a3b8; font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; overflow:hidden; text-overflow:ellipsis; }
#device-show-page .log-message { flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; color:#2c3e50; font-weight:500; }
#device-show-page .log-dur { flex:0 0 auto; margin-left:auto; background:#f1f5f9; color:#475569; border-radius:999px; padding:1px 8px; font-size:.7rem; font-variant-numeric:tabular-nums; white-space:nowrap; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; }
#device-show-page .log-power .log-icon-wrapper { background:#fff3cd; color:#b8860b; }
#device-show-page .log-success .log-icon-wrapper { background:#d1fae5; color:#059669; }
#device-show-page .log-warning .log-icon-wrapper { background:#fee2e2; color:#dc2626; }
#device-show-page .log-empty { padding:14px 15px; color:#94a3b8; }

/* --- Bagian dalam kartu gauge (pola sistem lama) --- */
.gauge-title { font-weight:600; color:#2c3e50; margin-bottom:10px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; text-align:center; }
.gauge-header-container { display:flex; align-items:center; justify-content:space-between; width:100%; height:28px; margin-bottom:6px; }
.pump-indicator { width:10px; height:10px; border-radius:9999px; background:#cbd5e1; flex:none; }
.pump-indicator.on { background:#27ae60; box-shadow:0 0 0 3px rgba(39,174,96,.2); animation:pumpPulse 1.8s ease-in-out infinite; }
.pump-duration-badge { font-size:.72rem; font-weight:700; color:#2c3e50; background:#f1f5f9; border-radius:9999px; padding:2px 8px; font-family:monospace; }
.pump-duration-badge.is-on { background:#27ae60; color:#fff; }
.pump-duration-badge.is-off { background:#7f8c8d; color:#fff; }
/* Badge tipe perangkat (MON / ACT) — berdampingan dengan indikator pompa di header kartu */
.hdr-left { display:inline-flex; align-items:center; gap:5px; flex:none; }
.device-type-badge { font-size:.62rem; font-weight:700; letter-spacing:.04em; border-radius:4px; padding:1px 5px; border:1px solid transparent; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; flex:none; }
.device-type-badge.is-mon { background:#eef2ff; color:#4338ca; border-color:#c7d2fe; }
.device-type-badge.is-act { background:#fff7ed; color:#c2410c; border-color:#fed7aa; }
/* P6: tipe penuh (MONITOR/ACTUATOR) hanya ditampilkan di layar kecil — di layar lebar
   label pendek MON/ACT tetap dipakai supaya kartu gauge tidak melebar. */
.device-type-badge .dtype-full { display:none; }
.signal-indicator { font-size:.75rem; font-weight:700; color:#7f8c8d; }
.pump-info-label { display:flex; align-items:center; justify-content:center; gap:8px; margin-top:6px; font-size:.85rem; color:#34495e; }
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
.gauge-mac { display:block; text-align:center; color:#94a3b8; font-family:monospace; font-size:.7rem; margin-top:6px; }

@keyframes pumpPulse { 0%,100% { opacity:1; } 50% { opacity:.45; } }
@keyframes fanSpin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }

/* --- Responsif: kartu statistik tetap SATU baris di semua ukuran layar --- */
@media (min-width:768px) {
    #device-show-page .stat-cards-container { gap:10px; }
    #device-show-page .stat-card { padding:12px 8px; }
    #device-show-page .stat-card-icon { width:36px; height:36px; font-size:1rem; margin-bottom:2px; }
    #device-show-page .stat-card-title { font-size:.68rem; }
    #device-show-page .stat-card-value { font-size:1.05rem; }
}
@media (min-width:1200px) {
    #device-show-page .stat-cards-container { gap:15px; }
    #device-show-page .stat-card { padding:14px 10px; }
    #device-show-page .stat-card-value { font-size:1.25rem; }
    #device-show-page .controller-detail-grid { grid-template-columns:350px 1fr; }
}

/* ==========================================================================
   RESPONSIF MOBILE/HP — ambang 480px (lihat TODO §7.27):
   ≤480px memakai tata letak ringkas; 481px ke atas mendapat ruang lebih lega.
   (hasil analisa awal — TODO §7.25)
   Lebar konten halaman = viewport − 106px (main p-5 40 + padding .card 40
   + border .log-list 2 + padding .log-item 24).
   ========================================================================== */

/* P2: header kartu grafik menumpuk → judul, kontrol, lalu canvas.
   Grid asli `1fr auto` tanpa media query membuat kontrol meluber / tombol
   mengepak sembarangan di layar ≤480px. */
@media (max-width:480px) {
    #device-show-page .chart-card-container { grid-template-columns:1fr; }
    #device-show-page .chart-controls-container { grid-column:1 / -1; grid-row:auto; }
    #device-show-page .chart-controls-container .btn-group { flex-wrap:wrap; }
    #device-show-page .chart-canvas-container { grid-column:1 / -1; }
}

/* P3: target sentuh (tombol rentang grafik & kontrol pompa) — semula ±26px,
   kini ±36px; checkbox Auto diperbesar agar mudah ditekan. */
@media (max-width:480px) {
    #device-show-page .btn-sm { padding:9px 12px; font-size:.78rem; }
    #device-show-page .gauge-actions .btn-action { padding:9px 14px; }
    #device-show-page .auto-scale-wrapper input { width:18px; height:18px; }
    #device-show-page .auto-scale-wrapper label { padding:9px 4px; }
}

/* P4: ruang vertikal lebih hemat di layar kecil — padding kartu & gauge dikecilkan
   (backup memakai 12px), tinggi kanvas dikurangi, judul halaman diperkecil
   (tidak disembunyikan agar konteks perangkat tetap terlihat). */
@media (max-width:480px) {
    #device-show-page .card { padding: 12px; }
    #device-show-page .card + .card { margin-top: 12px; }
    #device-show-page .controller-detail-grid { gap: 12px; margin-bottom: 12px; }
    #device-show-page #gauge-container { padding: 12px; min-height: 360px; }
    #device-show-page .chart-canvas-container { height: 220px; }
    #device-show-page .page-header { margin-bottom: 12px; }
    #device-show-page .page-header h1 { font-size: 1.05rem; }
}

/* P5: kartu statistik dibungkus 4 per baris (selaras dengan dashboard §7.23) — 7 kartu
   menjadi 2 baris TANPA scroll horizontal; judul tetap tampil (clamp 2 baris).
   P6: di layar kecil tampil tipe penuh (MONITOR/ACTUATOR), label pendek disembunyikan. */
@media (max-width:480px) {
    #device-show-page .stat-cards-container {
        grid-auto-flow: row;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 5px;
        overflow-x: visible;
    }
    #device-show-page .stat-card { padding: 8px 4px; }
    #device-show-page .stat-card-icon { width: 32px; height: 32px; font-size: .85rem; }
    #device-show-page .stat-card-title { font-size: .58rem; }
    #device-show-page .stat-card-value { font-size: .8rem; }
    #device-show-page .device-type-badge .dtype-short { display: none; }
    #device-show-page .device-type-badge .dtype-full { display: inline; }
}

/* P1: baris log jadi DUA baris agar pesan & chip durasi tetap terlihat.
   Pengecualian ambang: P1 memakai 640px (bukan 480px) karena baris satu-laris butuh
   ≈384px (ikon 22 + waktu 128 + tipe 84 + 4 gap + chip ~110) sedangkan lebar konten
   = viewport − 106px. Pengukuran nyata (Chrome headless/CDP) menunjukkan pada
   481–540px pesan menyusut ke ~0px, jadi tata letak 2-baris tetap dipakai sampai 640px.
   Dengan flex-wrap: baris-1 = ikon + waktu + tipe, baris-2 = pesan + chip durasi. */
@media (max-width:640px) {
    #device-show-page .log-item { flex-wrap:wrap; gap:6px 10px; padding:7px 10px; }
    #device-show-page .log-time { flex:0 0 auto; font-size:.7rem; }
    #device-show-page .log-type { flex:0 0 auto; font-size:.62rem; }
    /* Chip dir sedikit dikecilkan agar pesan + chip tetap muat dalam baris kedua */
    #device-show-page .log-dur { font-size:.62rem; padding:1px 6px; }
}
</style>
@endpush

@section('content')
<div id="device-show-page">
    <div class="page-header">
        <h1>Detail Perangkat — {{ $device->pump?->pump_name ?? $device->mac_address }}</h1>
        <a href="{{ route('devices.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Daftar</a>
    </div>

    {{-- Statistik Utama — SATU baris penuh; id elemen sama dengan sistem lama agar live update bekerja --}}
    <div class="stat-cards-container">
        {{-- 0. Level Air --}}
        <div class="stat-card">
            <div id="stat-water-icon" class="stat-card-icon bg-gray"><i class="fas fa-tint"></i></div>
            <div>
                <div class="stat-card-title">Level Air</div>
                <div id="stat-water-value" class="stat-card-value">{{ round($latestWaterPct) }}%</div>
            </div>
        </div>
        {{-- 1. Status Pompa --}}
        <div class="stat-card">
            <div id="stat-pump-icon" class="stat-card-icon bg-gray"><i class="fas fa-power-off"></i></div>
            <div>
                <div class="stat-card-title">Status Pompa (24j)</div>
                <div id="stat-pump-value" class="stat-card-value">{{ $device->isOnline() ? ($device->status ?: '-') : 'OFF' }}</div>
            </div>
        </div>
        {{-- 2. Mode Operasi --}}
        <div class="stat-card">
            <div class="stat-card-icon bg-blue"><i class="fas fa-sliders-h"></i></div>
            <div>
                <div class="stat-card-title">Mode Operasi</div>
                <div id="stat-mode-value" class="stat-card-value">{{ $device->control_mode }}</div>
            </div>
        </div>
        {{-- 3. Konektivitas --}}
        <div class="stat-card">
            <div id="stat-conn-icon" class="stat-card-icon {{ $device->isOnline() ? 'bg-green' : 'bg-gray' }}"><i class="fas fa-wifi"></i></div>
            <div>
                <div class="stat-card-title">Konektivitas</div>
                <div id="stat-conn-value" class="stat-card-value">{{ $device->isOnline() ? 'Online' : 'Offline' }}</div>
            </div>
        </div>
        {{-- 4. Sinyal WiFi --}}
        <div class="stat-card">
            <div class="stat-card-icon bg-orange"><i class="fas fa-wifi"></i></div>
            <div>
                <div class="stat-card-title">Sinyal WiFi</div>
                <div id="stat-signal-value" class="stat-card-value">{{ (int) ($device->rssi ?? 0) }} dBm</div>
            </div>
        </div>
        {{-- 5. Frekuensi Nyala (24j) --}}
        <div class="stat-card">
            <div class="stat-card-icon bg-orange"><i class="fas fa-sync"></i></div>
            <div>
                <div class="stat-card-title">Frekuensi Nyala</div>
                <div id="stat-cycle-value" class="stat-card-value">{{ (int) ($pump24['cycle_count'] ?? 0) }}x</div>
            </div>
        </div>
        {{-- 6. Durasi Nyala (24j) --}}
        <div class="stat-card">
            <div class="stat-card-icon bg-blue"><i class="fas fa-stopwatch"></i></div>
            <div>
                <div class="stat-card-title">Durasi (24j)</div>
                <div id="stat-duration-24h-value" class="stat-card-value">{{ $pump24['formatted'] ?? '00:00' }}</div>
            </div>
        </div>
    </div>

    <div class="controller-detail-grid">
        {{-- Kolom Kiri: Gauge &amp; Kontrol --}}
        <div id="gauge-container">
            <div id="gauge-card-{{ (int) $device->id }}" class="gauge-card"
                 data-template="{{ $gaugeTemplate ? json_encode($gaugeTemplate->only(['html_code', 'css_code', 'js_code']), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) : 'null' }}"></div>

            {{-- Aset & Sumber Data — tepat di bawah gauge, setelah label MAC (.gauge-mac);
                 di luar #gauge-card-* karena renderGaugeCardStructure() mengisi innerHTML kartu --}}
            <div class="info-block">
                <span class="info-block-title">Aset &amp; Sumber Data</span>
                <ul class="detail-list">
                    <li><span class="k">Nama Pompa</span><span class="v" id="val-pump-name">{{ $device->pump?->pump_name ?? 'N/A' }}</span></li>
                    <li><span class="k">Tangki</span><span class="v">{{ $device->tank?->tank_name ?? '-' }}</span></li>
                    @php
                        // Sumber level air = sensor terdaftar. MONITOR memakai sensor miliknya sendiri;
                        // ACTUATOR memakai sensor milik perangkat MONITOR pada tangki yang sama
                        // (interlock `DeviceApiController::tankMonitor()`). Penjelasan peran perangkat
                        // sudah ada di baris "Tipe Perangkat", jadi di sini cukup nama sensornya.
                        $sumberSensor = $device->sensor?->sensor_name;
                        if ($device->device_type !== 'MONITOR' && $device->tank_id) {
                            $sumberSensor = \App\Models\Device::where('tank_id', $device->tank_id)
                                ->where('device_type', 'MONITOR')
                                ->where('id', '!=', $device->id)
                                ->first()?->sensor?->sensor_name;
                        }
                    @endphp
                    <li><span class="k">Sumber Level Air</span><span class="v" id="val-data-source">{{ $sumberSensor ?: 'Belum ada sensor terdaftar' }}</span></li>
                    <li><span class="k">Sinkron Offline</span><span class="v">{{ $device->last_offline_sync?->format('d-m-Y H:i:s') ?? '-' }}</span></li>
                </ul>
            </div>
        </div>

        {{-- Kolom Kanan: Detail Konfigurasi --}}
        <div class="card">
            <h2><i class="fas fa-cogs"></i> Detail Konfigurasi</h2>

            <div class="info-block">
                <span class="info-block-title">Konfigurasi Teknis</span>
                <ul class="detail-list">
                    <li><span class="k">MAC Address</span><span class="v" id="val-mac">{{ $device->mac_address }}</span></li>
                    <li><span class="k">Versi Firmware</span><span class="v" id="val-firmware">{{ $device->firmware_version ?: 'N/A' }}@if($device->firmware_build_date) ({{ $device->firmware_build_date }})@endif</span></li>
                    <li><span class="k">Tipe Perangkat</span><span class="v">{{ $device->device_type }}@if($device->device_type === 'MONITOR') (sensor + pompa, fungsi ganda)@else (pompa saja, tanpa baca sensor)@endif</span></li>
                    @php
                        // Firmware mengirim `uptime` dalam MILIDETIK (`millis()` — Network_SSL.ino:90,
                        // juga firmware sistem lama), sedangkan tampilan memakai satuan DETIK.
                        // Format di sini disamakan persis dengan fmtUptime() di JS agar angka tidak
                        // "melompat" saat poll pertama datang.
                        $uptimeSec = intdiv((int) ($device->uptime ?? 0), 1000);
                        $upDays = intdiv($uptimeSec, 86400);
                        $upHours = intdiv($uptimeSec % 86400, 3600);
                        $upMins = intdiv($uptimeSec % 3600, 60);
                        $uptimeText = ($upDays > 0 ? $upDays . ' hari ' : '')
                            . ($upDays > 0 || $upHours > 0 ? $upHours . ' jam ' : '')
                            . $upMins . ' menit';
                    @endphp
                    <li><span class="k">Waktu Nyala</span><span class="v" id="val-uptime">{{ $uptimeText }}</span></li>
                    <li><span class="k">Free Heap</span><span class="v" id="val-heap">{{ number_format(($device->free_heap ?? 0) / 1024, 1) }} KB</span></li>
                    <li><span class="k">Reset Terakhir</span><span class="v" id="val-reset-reason">{{ $device->reset_reason ?: '-' }}</span></li>
                    <li><span class="k">Update Terakhir</span><span class="v" id="val-last-update">{{ $device->last_update?->format('d-m-Y H:i:s') ?? 'N/A' }}</span></li>
                </ul>
            </div>

            <div class="info-block">
                <span class="info-block-title">Timer &amp; Ambang Batas</span>
                <ul class="detail-list">
                    <li><span class="k">Durasi Nyala Maks</span><span class="v" id="val-on-duration">{{ (int) $device->on_duration }} menit</span></li>
                    <li><span class="k">Durasi Istirahat Min</span><span class="v" id="val-off-duration">{{ (int) $device->off_duration }} menit</span></li>
                    <li><span class="k">Pemicu Otomatis</span><span class="v">{{ (int) ($device->trigger_percentage ?? 0) }}%</span></li>
                    <li><span class="k">Jarak Penuh / Kosong</span><span class="v">{{ (int) $device->full_tank_distance }} cm / {{ (int) $device->empty_tank_distance }} cm</span></li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Grafik Riwayat Level Air &amp; Pompa (full width) --}}
    <div class="card">
        <div class="chart-card-container">
            <h2 class="chart-title"><i class="fas fa-chart-line"></i> Riwayat Level Air &amp; Pompa</h2>
            <div class="chart-controls-container">
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-secondary chart-btn" data-range="live">Live</button>
                    <button type="button" class="btn btn-sm btn-secondary chart-btn active" data-range="60">1 Jam</button>
                    <button type="button" class="btn btn-sm btn-secondary chart-btn" data-range="360">6 Jam</button>
                    <button type="button" class="btn btn-sm btn-secondary chart-btn" data-range="1440">24 Jam</button>
                </div>
                <div class="auto-scale-wrapper">
                    <input type="checkbox" id="autoScaleToggle">
                    <label for="autoScaleToggle">Auto</label>
                </div>
            </div>
            <div class="chart-canvas-container">
                <canvas id="waterLevelChart" data-device-id="{{ (int) $device->id }}" data-trigger="{{ (int) ($device->trigger_percentage ?? 70) }}"></canvas>
            </div>
        </div>
    </div>

    {{-- Log Kejadian Terakhir --}}
    <div class="card log-container">
        <h2><i class="fas fa-history"></i> Log Kejadian Terakhir</h2>
        @php
            // Durasi nyala/mati tiap transisi pompa dihitung dari pump_logs (TANPA mengubah
            // database; kolom duration_seconds memang tidak pernah diisi). Dipetakan per waktu
            // supaya bisa dicocokkan dengan entri kejadian bertipe "Pump" di daftar bawah.
            $pumpDurTime = [];
            foreach (\App\Support\PumpDuration::mapFromLogs($pumpLogs) as $pd) {
                $pumpDurTime[$pd['waktu']] = $pd;
            }
        @endphp
        <ul class="log-list" id="event-log-list">
            @forelse($eventLogs as $log)
                @php
                    $msg = strtolower((string) $log->message);
                    // Durasi nyala/mati entri Pump: dicocokkan lewat waktu kejadian yang sama.
                    $durKey = $log->event_time ? \Carbon\Carbon::parse($log->event_time)->format('Y-m-d H:i:s') : null;
                    $dur = ($log->event_type === 'Pump' && $durKey) ? ($pumpDurTime[$durKey] ?? null) : null;
                    $colorClass = '';
                    $icon = 'fa-info-circle';
                    if (str_contains($msg, 'tersambung')) { $colorClass = 'log-success'; $icon = 'fa-wifi'; }
                    elseif (str_contains($msg, 'terputus')) { $colorClass = 'log-warning'; $icon = 'fa-unlink'; }
                    elseif (str_contains($msg, 'boot')) { $colorClass = 'log-power'; $icon = 'fa-bolt'; }
                    elseif (str_contains($msg, 'nyala')) { $colorClass = 'log-success'; $icon = 'fa-power-off'; }
                    elseif (str_contains($msg, 'mati')) { $colorClass = 'log-warning'; $icon = 'fa-power-off'; }
                @endphp
                <li class="log-item {{ $colorClass }}" title="{{ $log->event_type }} · {{ $log->message }}">
                    <span class="log-icon-wrapper"><i class="fas {{ $icon }}"></i></span>
                    <span class="log-time">{{ $log->event_time ? \Carbon\Carbon::parse($log->event_time)->format('d-m-Y H:i:s') : '-' }}</span>
                    <span class="log-type">{{ $log->event_type }}</span>
                    <span class="log-message">{{ $log->message }}</span>
                    @if($dur)
                        <span class="log-dur" title="{{ $dur['dari'] === 'ON' ? 'Durasi nyala sebelum pompa dimatikan' : 'Durasi mati/istirahat sebelum pompa menyala' }}">{{ $dur['dari'] === 'ON' ? 'nyala' : 'mati' }} {{ $dur['teks'] }}</span>
                    @endif
                </li>
            @empty
                <li class="log-empty">Belum ada log tersedia.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.0.1/dist/chartjs-adapter-moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
<script>
    // Konfigurasi perangkat (pola sistem lama: window.DEVICE_CONFIG)
    window.DEVICE_CONFIG = {
        id: {{ (int) $device->id }},
        mac: @json($device->mac_address),
        tankName: @json($device->tank?->tank_name ?? 'Bak'),
        pumpName: @json($device->pump?->pump_name ?? 'Pompa'),
        isMonitor: {{ $device->sensor_id ? 'true' : 'false' }},
        deviceType: @json($device->device_type),
        initialPct: {{ (float) $latestWaterPct }},
        pumpStatusSince: {{ $pumpStatusSince !== null ? (int) $pumpStatusSince : 'null' }},
        pumpStatus: @json($device->status),
        isOnline: {{ $device->isOnline() ? 'true' : 'false' }},
        lastUpdateTs: {{ (int) ($device->last_update?->timestamp ?? 0) }}
    };
    window.indicatorSettings = @json($indicator_settings);
    window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
</script>
@verbatim
<script>
(function () {
    'use strict';

    const CFG = window.DEVICE_CONFIG || {};
    const IS = window.indicatorSettings || {};
    const DEVICE_ID = CFG.id;

    const TPL = (function () {
        const el = document.getElementById('gauge-card-' + DEVICE_ID);
        if (!el) return null;
        try { return JSON.parse(el.dataset.template || 'null'); } catch (e) { return null; }
    })();

    const LOW = parseFloat(IS.threshold_low ?? 30);
    const MID = parseFloat(IS.threshold_medium ?? 70);
    const C_LOW = IS.color_low || '#e74c3c';
    const C_MID = IS.color_medium || '#f39c12';
    const C_HIGH = IS.color_high || '#27ae60';

    // Timer durasi: hijau = lama nyala (ON), abu = lama mati (OFF) — count-up, bukan countdown
    // Saat device offline, status terakhir TIDAK berlaku → dianggap OFF, dihitung sejak kontak terakhir
    let pumpOnSince = null;
    let pumpOffSince = null;
    const bootOnline = CFG.isOnline !== false;
    let lastPumpState = bootOnline ? (CFG.pumpStatus || null) : 'OFF';
    if (bootOnline && CFG.pumpStatusSince) {
        if (lastPumpState === 'ON') pumpOnSince = CFG.pumpStatusSince * 1000;
        else if (lastPumpState === 'OFF') pumpOffSince = CFG.pumpStatusSince * 1000;
    }
    if (!bootOnline) {
        pumpOffSince = CFG.lastUpdateTs > 0 ? CFG.lastUpdateTs * 1000 : Date.now();
    }

    const $ = (id) => document.getElementById(id);
    function setText(id, text) { const el = $(id); if (el) el.textContent = text; }
    function colorOf(pct) { return pct > MID ? C_HIGH : (pct > LOW ? C_MID : C_LOW); }
    function pad(n) { return String(n).padStart(2, '0'); }
    function fmtClock(totalSec) {
        totalSec = Math.max(0, Math.floor(totalSec));
        return pad(Math.floor(totalSec / 3600)) + ':' + pad(Math.floor((totalSec % 3600) / 60)) + ':' + pad(totalSec % 60);
    }
    function fmtUptime(totalSec) {
        totalSec = Math.max(0, Math.floor(Number(totalSec) || 0));
        const d = Math.floor(totalSec / 86400), h = Math.floor((totalSec % 86400) / 3600), m = Math.floor((totalSec % 3600) / 60);
        const parts = [];
        if (d) parts.push(d + ' hari');
        if (h || d) parts.push(h + ' jam');
        parts.push(m + ' menit');
        return parts.join(' ');
    }
    function fmtBytes(bytes) {
        bytes = Number(bytes) || 0;
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return bytes + ' B';
    }
    function fmtTime(value) {
        if (!value) return 'N/A';
        const d = new Date(String(value).replace(' ', 'T'));
        if (isNaN(d.getTime())) return String(value);
        return d.toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }


    /* ------------------------- GAUGE (template aktif) ------------------------- */
    function injectTemplateAssets() {
        if (!TPL) return;
        if (TPL.css_code && !document.getElementById('gauge-template-css')) {
            const st = document.createElement('style');
            st.id = 'gauge-template-css';
            st.textContent = TPL.css_code;
            document.head.appendChild(st);
        }
        if (TPL.js_code && !window.__gaugeTplJsLoaded) {
            try { (new Function(TPL.js_code))(); window.__gaugeTplJsLoaded = true; }
            catch (e) { console.error('Gauge template JS error:', e); }
        }
    }

    /**
     * Badge tipe perangkat: MON = MONITOR (sensor + pompa), ACT = ACTUATOR (pompa saja).
     * Tipe lain (tak dikenal) tidak ditampilkan.
     */
    function deviceTypeInfo(type) {
        const t = String(type || '').toUpperCase();
        if (t === 'MONITOR') return { label: 'MON', full: 'MONITOR', cls: 'is-mon', title: 'Tipe perangkat: MONITOR — sensor + pompa (fungsi ganda)' };
        if (t === 'ACTUATOR') return { label: 'ACT', full: 'ACTUATOR', cls: 'is-act', title: 'Tipe perangkat: ACTUATOR — pompa saja (tanpa baca sensor)' };
        return null;
    }
    function deviceTypeBadgeHtml(type) {
        const info = deviceTypeInfo(type);
        if (!info) return '';
        return '<span class="device-type-badge ' + info.cls + '" data-device-type title="' + info.title + '">'
            + '<span class="dtype-short">' + info.label + '</span>'
            + '<span class="dtype-full">' + info.full + '</span></span>';
    }

    /**
     * Bangun struktur kartu gauge (header indikator, label pompa, tombol aksi, MAC)
     * — pola renderGaugeCardStructure() sistem lama.
     */
    function renderGaugeCardStructure(card) {
        if (!card) return;
        if (!TPL || !TPL.html_code) {
            card.innerHTML = '<p style="text-align:center;color:#94a3b8;font-size:.85rem;">Template gauge belum tersedia.</p>';
            return;
        }
        card.innerHTML = TPL.html_code
            .replace(/{{\s*TANK[ _-]*NAME\s*}}/gi, CFG.tankName || 'Bak')
            .replace(/{{\s*DEVICE[ _-]*ID\s*}}/gi, String(DEVICE_ID))
            .replace(/{{\s*PUMP[ _-]*NAME\s*}}/gi, CFG.pumpName || 'Pompa');

        const header = document.createElement('div');
        header.className = 'gauge-header-container';
        header.innerHTML = '<span class="hdr-left">'
            + '<span class="pump-indicator" data-pump-led></span>'
            + deviceTypeBadgeHtml(CFG.deviceType)
            + '</span>'
            + '<span class="pump-duration-badge" data-pump-timer>--:--:--</span>'
            + '<span class="signal-indicator" data-signal>--</span>';
        card.prepend(header);

        const info = document.createElement('div');
        info.className = 'pump-info-label';
        info.innerHTML = '<i class="fas fa-fan" data-fan></i><span>' + (CFG.pumpName || 'Pompa') + '</span>';
        card.appendChild(info);

        const actions = document.createElement('div');
        actions.className = 'gauge-actions';
        actions.innerHTML = '<button type="button" class="btn-action btn-blue" data-mode-toggle data-mac="' + (CFG.mac || '') + '">AUTO</button>'
            + '<button type="button" class="btn-action btn-red" data-pump-toggle data-mac="' + (CFG.mac || '') + '">--</button>';
        card.appendChild(actions);

        const mac = document.createElement('small');
        mac.className = 'gauge-mac';
        mac.textContent = CFG.mac || '';
        card.appendChild(mac);

        if (typeof window.initGauge === 'function') {
            try { window.initGauge(card); } catch (e) { console.error('Gauge init error:', e); }
        }
    }

    /**
     * Updater universal gauge (pola gauge-init.js sistem lama): membaca atribut
     * data-update-style pada HTML template. Dipakai bila template tidak punya js_code.
     */
    function universalUpdateGauge(cardElement, waterLevel, fillColor) {
        cardElement.querySelectorAll('[data-update-style]').forEach(function (el) {
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
        if (textElement) textElement.textContent = Math.round(waterLevel) + '%';
    }

    function paintGauge(card, pct) {
        if (!card) return;
        const color = colorOf(pct);
        if (typeof window.updateGauge === 'function') {
            try { window.updateGauge(card, pct, color); } catch (e) { console.error('Gauge update error:', e); }
        } else {
            universalUpdateGauge(card, pct, color);
        }
    }


    /* --------------------- UPDATE UI DARI DATA LIVE API --------------------- */
    function applyDeviceState(d) {
        if (!d) return;
        const pct = Math.max(0, Math.min(100, Number(d.water_percentage) || 0));
        const isOn = String(d.status || '').toUpperCase() === 'ON';
        const online = !!d.is_online;
        const mode = String(d.control_mode || 'AUTO').toUpperCase();
        const rssi = Number(d.rssi) || 0;

        // Kartu statistik
        setText('stat-water-value', Math.round(pct) + '%');
        const wi = $('stat-water-icon');
        if (wi) wi.className = 'stat-card-icon ' + (pct > MID ? 'bg-green' : (pct > LOW ? 'bg-orange' : 'bg-red'));

        // Offline: status terakhir tidak berlaku → tampilan dianggap OFF (konsisten dengan timer & grafik)
        const pumpDisplayOn = isOn && online;
        setText('stat-pump-value', pumpDisplayOn ? 'ON' : 'OFF');
        const pi = $('stat-pump-icon');
        if (pi) pi.className = 'stat-card-icon ' + (pumpDisplayOn ? 'bg-green' : 'bg-gray');

        setText('stat-mode-value', mode);
        setText('stat-conn-value', online ? 'Online' : 'Offline');
        const ci = $('stat-conn-icon');
        if (ci) ci.className = 'stat-card-icon ' + (online ? 'bg-green' : 'bg-gray');
        setText('stat-signal-value', rssi + ' dBm');

        // Detail konfigurasi
        // CATATAN: `/api/dashboard-data` mengirim `uptime` dalam MILIDETIK (nilai `millis()`
        // perangkat), sedangkan fmtUptime() memakai detik -> konversi di sini.
        setText('val-uptime', fmtUptime(Math.floor((Number(d.uptime) || 0) / 1000)));
        setText('val-heap', fmtBytes(d.free_heap));
        setText('val-last-update', fmtTime(d.last_update));
        if (typeof d.reset_reason !== 'undefined') setText('val-reset-reason', d.reset_reason || '-');
        if (d.pump_name) setText('val-pump-name', d.pump_name);

        // Kartu gauge
        const card = $('gauge-card-' + DEVICE_ID);
        if (card) {
            paintGauge(card, pct);

            const led = card.querySelector('[data-pump-led]');
            if (led) led.className = 'pump-indicator' + (isOn && online ? ' on' : '');

            // Badge tipe perangkat (MON / ACT) — disegarkan bila API mengirim device_type
            const typeBadge = card.querySelector('[data-device-type]');
            if (typeBadge && d.device_type) {
                const info = deviceTypeInfo(d.device_type);
                typeBadge.textContent = info ? info.label : '';
                typeBadge.title = info ? info.title : '';
                typeBadge.className = 'device-type-badge' + (info ? ' ' + info.cls : '');
                typeBadge.style.display = info ? '' : 'none';
            }

            const fan = card.querySelector('[data-fan]');
            if (fan) fan.className = 'fas fa-fan' + (isOn && online ? ' fa-spin' : '');

            const sig = card.querySelector('[data-signal]');
            if (sig) {
                sig.textContent = rssi ? (rssi + ' dBm') : '--';
                sig.style.color = rssi >= -60 ? '#27ae60' : (rssi >= -75 ? '#f39c12' : '#e74c3c');
            }

            const modeBtn = card.querySelector('[data-mode-toggle]');
            if (modeBtn) {
                modeBtn.textContent = mode;
                modeBtn.className = 'btn-action ' + (mode === 'AUTO' ? 'btn-blue' : 'btn-gray');
                modeBtn.dataset.mode = mode;
                modeBtn.disabled = !online;
                modeBtn.title = 'Mode kontrol sekarang: ' + mode + ' — klik untuk mengubah';
            }

            const pumpBtn = card.querySelector('[data-pump-toggle]');
            if (pumpBtn) {
                pumpBtn.textContent = isOn ? 'ON' : 'OFF';
                // Warna mengikuti dashboard: abu (offline) / hijau (ON) / merah (OFF)
                pumpBtn.className = 'btn-action ' + (!online ? 'btn-gray' : (isOn ? 'btn-green' : 'btn-red'));
                pumpBtn.dataset.status = isOn ? 'ON' : 'OFF';
                pumpBtn.disabled = !online || mode !== 'MANUAL';
                pumpBtn.title = !online
                    ? 'Perangkat offline — perintah tidak dapat dikirim'
                    : (mode !== 'MANUAL'
                        ? 'Mode AUTO: pompa dikendalikan otomatis. Ubah ke MANUAL untuk kontrol manual.'
                        : 'Pompa sekarang ' + (isOn ? 'ON' : 'OFF') + ' — klik untuk ' + (isOn ? 'mematikan' : 'menyalakan'));
            }
        }

        // Badge timer durasi: hijau = lama nyala, abu = lama mati (count-up)
        // Sumber utama: pump_status_since dari API (waktu transisi terakhir); fallback lokal.
        // Saat device offline: status terakhir TIDAK berlaku → pompa dianggap OFF dan timer
        // dihitung sejak kontak terakhir (last_update_ts), bukan tetap hijau ON.
        if (online) {
            const sinceTs = Number(d.pump_status_since) || 0;
            if (isOn) {
                if (sinceTs > 0) pumpOnSince = sinceTs * 1000;
                else if (pumpOnSince === null || lastPumpState === 'OFF') pumpOnSince = Date.now();
            } else {
                if (sinceTs > 0) pumpOffSince = sinceTs * 1000;
                else if (pumpOffSince === null || lastPumpState === 'ON') pumpOffSince = Date.now();
            }
            lastPumpState = isOn ? 'ON' : 'OFF';
        } else {
            const contactMs = (Number(d.last_update_ts) || 0) * 1000;
            if (contactMs > 0) pumpOffSince = contactMs;
            else if (pumpOffSince === null || lastPumpState === 'ON') pumpOffSince = Date.now();
            lastPumpState = 'OFF';
        }
        const tEl = card ? card.querySelector('[data-pump-timer]') : null;
        if (tEl) {
            tEl.title = online
                ? (isOn ? 'Lama waktu pompa nyala (timer berjalan)' : 'Lama waktu pompa mati (timer berjalan)')
                : 'Perangkat offline — pompa dianggap mati; timer dihitung sejak kontak terakhir';
        }
    }

    async function pollDevice() {
        try {
            const res = await fetch('/api/dashboard-data', { headers: { 'Accept': 'application/json' } });
            if (res.status === 401) { window.location.href = '/login'; return; } // sesi berakhir → login ulang
            const data = await res.json();
            const d = (data.devices || []).find((x) => Number(x.id) === Number(DEVICE_ID));
            if (d) applyDeviceState(d);
        } catch (e) {
            console.error('Gagal memuat data live perangkat:', e);
        }
    }

    async function sendCommand(mac, action, value) {
        try {
            const res = await fetch('/api/device-command', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN
                },
                body: JSON.stringify({ mac: mac, action: action, value: value })
            });
            const out = await res.json().catch(() => ({}));
            if (!res.ok) alert(out.message || ('Gagal mengirim perintah (HTTP ' + res.status + ')'));
        } catch (err) {
            alert('Gagal mengirim perintah: ' + err.message);
        }
        pollDevice();
    }

    document.addEventListener('click', function (e) {
        const modeBtn = e.target.closest('[data-mode-toggle]');
        if (modeBtn && !modeBtn.disabled) {
            e.preventDefault();
            const cur = String(modeBtn.dataset.mode || 'AUTO').toUpperCase();
            sendCommand(modeBtn.dataset.mac, 'set_mode', cur === 'MANUAL' ? 'AUTO' : 'MANUAL');
            return;
        }
        const pumpBtn = e.target.closest('[data-pump-toggle]');
        if (pumpBtn && !pumpBtn.disabled) {
            e.preventDefault();
            const next = String(pumpBtn.dataset.status || 'OFF').toUpperCase() === 'ON' ? 'OFF' : 'ON';
            sendCommand(pumpBtn.dataset.mac, 'set_pump', next);
        }
    });


    /* --------------------------- GRAFIK RIWAYAT --------------------------- */
    (function initChart() {
        const canvas = document.getElementById('waterLevelChart');
        if (!canvas) return;
        if (typeof Chart === 'undefined') {
            canvas.parentElement.innerHTML = '<p style="padding:32px 0;text-align:center;color:#94a3b8;font-size:.85rem;">Library grafik (Chart.js) tidak dapat dimuat.</p>';
            return;
        }
        if (window.ChartAnnotation) Chart.register(window.ChartAnnotation);
        else if (window['chartjs-plugin-annotation']) Chart.register(window['chartjs-plugin-annotation']);

        const deviceId = canvas.dataset.deviceId;
        const trigger = parseFloat(canvas.dataset.trigger || 70);
        const controls = document.querySelector('#device-show-page .chart-controls-container');
        const autoScaleToggle = document.getElementById('autoScaleToggle');

        let chart = null;
        let range = '60';
        let liveTimer = null;

        function boxAnnotation(xMin, xMax) {
            return {
                type: 'box', xMin: xMin, xMax: xMax,
                backgroundColor: 'rgba(46,204,113,.15)',
                borderColor: 'rgba(46,204,113,.5)',
                borderWidth: 1, drawTime: 'beforeDraw'
            };
        }

        function buildAnnotations(sensors, pumps, initialStatus, windowStart, windowEnd) {
            const boxes = {};
            const hasSensors = Array.isArray(sensors) && sensors.length > 0;
            let startTime = null;
            if (String(initialStatus || 'OFF').toUpperCase() === 'ON') {
                startTime = windowStart || (hasSensors ? sensors[0].record_time : null);
            }
            (pumps || []).forEach(function (p, i) {
                const status = String(p.status || 'OFF').toUpperCase();
                if (status === 'ON' && !startTime) startTime = p.record_time;
                else if (status === 'OFF' && startTime) { boxes['pumpBox' + i] = boxAnnotation(startTime, p.record_time); startTime = null; }
            });
            if (startTime) {
                const last = windowEnd || (hasSensors ? sensors[sensors.length - 1].record_time : null);
                boxes['pumpBoxLast'] = boxAnnotation(startTime, last);
            }
            boxes.triggerLine = {
                type: 'line', yMin: trigger, yMax: trigger,
                borderColor: 'rgba(243,156,18,.7)', borderWidth: 2, borderDash: [5, 5],
                label: { content: 'Trigger: ' + trigger + '%', enabled: true, position: 'start', font: { size: 10 }, backgroundColor: 'rgba(243,156,18,.7)', color: 'white' }
            };
            return boxes;
        }


        function updateChart(sensors, pumps, initialStatus, windowStart, windowEnd) {
            const hasSensors = Array.isArray(sensors) && sensors.length > 0;
            const hasPumps = Array.isArray(pumps) && pumps.length > 0;

            canvas.parentElement.querySelector('.no-data-note')?.remove();
            if (!hasSensors && !hasPumps) {
                if (chart) { chart.destroy(); chart = null; }
                const note = document.createElement('p');
                note.className = 'no-data-note';
                note.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:.85rem;';
                note.textContent = 'Tidak ada riwayat aktivitas (Sensor/Pompa).';
                canvas.parentElement.appendChild(note);
                return;
            }

            const datasets = [];
            if (hasSensors) {
                datasets.push({
                    label: 'Level Air (%)',
                    data: sensors.map(function (s) { return { x: s.record_time, y: s.water_percentage }; }),
                    borderColor: 'rgba(52,152,219,1)',
                    backgroundColor: 'rgba(52,152,219,.2)',
                    fill: true, tension: 0.3, pointRadius: 0, borderWidth: 2
                });
            }

            const isAuto = autoScaleToggle ? autoScaleToggle.checked : false;
            const numericRange = range === 'live' ? 60 : Number(range);
            const options = {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    tooltip: {
                        mode: 'index', intersect: false,
                        callbacks: {
                            title: function (ctx) { return moment(ctx[0].parsed.x).format('DD MMM YYYY, HH:mm'); },
                            label: function (ctx) { return ctx.dataset.label + ': ' + (Math.round(ctx.parsed.y * 10) / 10) + '%'; }
                        }
                    },
                    legend: { display: true, position: 'top' },
                    annotation: { annotations: buildAnnotations(sensors, pumps, initialStatus, windowStart, windowEnd) }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            parser: 'YYYY-MM-DD HH:mm:ss',
                            tooltipFormat: 'DD MMM YYYY, HH:mm',
                            unit: (range === 'live' || numericRange <= 180) ? 'minute' : (numericRange <= 1440 ? 'hour' : 'day'),
                            displayFormats: { minute: 'HH:mm', hour: 'HH:mm', day: 'DD MMM' }
                        },
                        title: { display: true, text: 'Waktu' },
                        ticks: { autoSkip: true, maxRotation: 0, minRotation: 0 }
                    },
                    y: {
                        beginAtZero: !isAuto,
                        min: isAuto ? undefined : 0,
                        max: 100,
                        title: { display: true, text: 'Level Air (%)' },
                        ticks: { callback: function (v) { return v + '%'; } }
                    }
                }
            };

            if (chart) {
                // Live refresh: JANGAN buat ulang chart dan JANGAN animasikan.
                // Chart.js memutar ulang animasi "tumbuh dari bawah" bila chart dibuat ulang
                // atau di-update dengan animasi aktif — karena itu tiap 5 detik grafik tampak
                // muncul dari bawah. Di sini cukup perbarui data + anotasi di tempat lalu
                // update('none') supaya garis hanya "bertambah panjang".
                // Animasi tumbuh-dari-bawah hanya terjadi saat chart pertama dibuat
                // (yaitu ketika halaman di-reload / chart belum ada).
                chart.data.datasets = datasets;
                chart.options.plugins.annotation.annotations = options.plugins.annotation.annotations;
                chart.options.scales.x.time.unit = options.scales.x.time.unit;
                chart.options.scales.y.beginAtZero = options.scales.y.beginAtZero;
                chart.options.scales.y.min = options.scales.y.min;
                chart.update('none');
            } else {
                chart = new Chart(canvas.getContext('2d'), { type: 'line', data: { datasets: datasets }, options: options });
            }
        }


        async function fetchChartData() {
            try {
                const res = await fetch('/api/device/history?device_id=' + encodeURIComponent(deviceId) + '&range=' + encodeURIComponent(range));
                if (res.status === 401) { window.location.href = '/login'; return; } // sesi berakhir → login ulang
                const data = await res.json().catch(function () { return {}; });
                if (!data || !data.sensors) return;
                updateChart(data.sensors, data.pumps, data.initial_pump_status, data.window_start, data.window_end);
            } catch (e) {
                console.error('Gagal memuat riwayat grafik:', e);
            }
        }

        if (controls) {
            controls.addEventListener('click', function (ev) {
                const btn = ev.target.closest('.chart-btn');
                if (!btn) return;
                if (liveTimer) { clearInterval(liveTimer); liveTimer = null; }
                controls.querySelectorAll('.chart-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                range = btn.dataset.range || '60';
                if (range === 'live') liveTimer = setInterval(fetchChartData, 5000);
                fetchChartData();
            });
        }
        if (autoScaleToggle) autoScaleToggle.addEventListener('change', fetchChartData);
        fetchChartData();
    })();

    /* ------------------------------ INISIALISASI ------------------------------ */
    injectTemplateAssets();
    const gaugeCard = $('gauge-card-' + DEVICE_ID);
    renderGaugeCardStructure(gaugeCard);
    if (gaugeCard) paintGauge(gaugeCard, Math.max(0, Math.min(100, Number(CFG.initialPct) || 0)));

    // Badge timer durasi (count-up): hijau saat ON, abu saat OFF — diperbarui tiap detik
    function updatePumpTimerBadge() {
        const card = $('gauge-card-' + DEVICE_ID);
        if (!card) return;
        const badge = card.querySelector('[data-pump-timer]');
        if (!badge) return;
        const isOn = lastPumpState === 'ON';
        const sinceMs = isOn ? pumpOnSince : pumpOffSince;
        badge.textContent = sinceMs ? fmtClock((Date.now() - sinceMs) / 1000) : '--:--:--';
        badge.classList.toggle('is-on', isOn);
        badge.classList.toggle('is-off', !isOn);
    }
    updatePumpTimerBadge();
    setInterval(updatePumpTimerBadge, 1000);

    // Polling data live (2 detik — pola dashboard-live.js sistem lama)
    pollDevice();
    setInterval(pollDevice, 2000);
})();
</script>
@endverbatim
@endpush

