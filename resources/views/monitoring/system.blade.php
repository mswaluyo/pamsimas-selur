@extends('layouts.app')
@section('title', 'Monitoring Sistem')

@section('content')
<div class="rounded-xl bg-white p-6 shadow">
    <h2 class="mb-4 font-semibold">Informasi Server</h2>
    <dl class="space-y-3 text-sm">
        @foreach([
            'PHP Version' => $phpVersion,
            'Laravel Version' => $laravelVersion,
            'Server Software' => $serverSoftware,
            'Memory (peak)' => $memoryUsage,
            'Disk Space' => "$diskFree free / $diskTotal total",
            'Timezone' => $timezone,
        ] as $label => $val)
        <div class="flex justify-between border-b pb-2"><dt class="text-slate-500">{{ $label }}</dt><dd class="font-medium">{{ $val }}</dd></div>
        @endforeach
    </dl>
</div>
@endsection