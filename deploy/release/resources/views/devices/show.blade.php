@extends('layouts.app')
@section('title', 'Detail Perangkat')

@section('content')
<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
    <div class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 font-semibold">Informasi Perangkat</h2>
        <dl class="space-y-2 text-sm">
            @foreach([
                'MAC Address' => $device->mac_address,
                'Tipe' => $device->device_type,
                'Mode' => $device->control_mode,
                'Status Pompa' => $device->status,
                'Tangki' => $device->tank?->tank_name,
                'Pompa' => $device->pump?->pump_name,
                'Sensor' => $device->sensor?->sensor_name,
                'Firmware' => $device->firmware_version . ' (' . $device->firmware_build_date . ')',
                'RSSI' => $device->rssi . ' dBm',
                'Uptime' => floor($device->uptime / 3600) . ' jam ' . floor(($device->uptime % 3600) / 60) . ' menit',
                'Free Heap' => number_format($device->free_heap / 1024, 1) . ' KB',
                'Reset Reason' => $device->reset_reason ?: '-',
                'Terakhir Update' => $device->last_update?->format('d-m-Y H:i:s'),
                'Sinkron Offline' => $device->last_offline_sync?->format('d-m-Y H:i:s') ?? '-',
            ] as $label => $val)
            <div class="flex justify-between border-b pb-1"><dt class="text-slate-500">{{ $label }}</dt><dd class="font-medium">{{ $val ?? '-' }}</dd></div>
            @endforeach
        </dl>
        <p class="mt-4 text-xs text-slate-400">Konfigurasi jarak: penuh {{ $device->full_tank_distance }} cm / kosong {{ $device->empty_tank_distance }} cm / trigger {{ $device->trigger_percentage }}%</p>
    </div>

    <div class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 font-semibold">Log Sensor Terbaru</h2>
        <div class="max-h-96 space-y-1 overflow-y-auto text-xs">
            @forelse($sensorLogs as $log)
            <div class="flex justify-between border-b py-1">
                <span>{{ $log->record_time->format('d-m H:i:s') }}</span>
                <span class="font-semibold">{{ number_format($log->water_percentage, 1) }}%</span>
            </div>
            @empty
            <p class="text-slate-400">Belum ada data.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 font-semibold">Log Pompa Terbaru</h2>
        <div class="max-h-96 space-y-1 overflow-y-auto text-xs">
            @forelse($pumpLogs as $log)
            <div class="flex justify-between border-b py-1">
                <span>{{ $log->timestamp->format('d-m H:i:s') }}</span>
                <span class="font-semibold {{ $log->pump_status === 'ON' ? 'text-emerald-600' : 'text-slate-500' }}">{{ $log->pump_status }} ({{ $log->control_mode }})</span>
            </div>
            @empty
            <p class="text-slate-400">Belum ada data.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
