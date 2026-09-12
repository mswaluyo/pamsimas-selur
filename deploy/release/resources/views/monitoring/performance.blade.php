@extends('layouts.app')
@section('title', 'Performansi')

@section('content')
<div class="grid grid-cols-1 gap-5 md:grid-cols-3">
    @foreach([
        ['Latensi Query', $queryMs . ' ms', '⚡'],
        ['Log Sensor (24 jam)', $sensorLogsLast24h, '📈'],
        ['Log Pompa (24 jam)', $pumpLogsLast24h, '⚙️'],
    ] as $card)
    <div class="flex items-center gap-4 rounded-xl bg-white p-5 shadow">
        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-500 text-2xl">{{ $card[2] }}</span>
        <div>
            <p class="text-sm text-slate-500">{{ $card[0] }}</p>
            <p class="text-2xl font-bold">{{ $card[1] }}</p>
        </div>
    </div>
    @endforeach
</div>
@endsection