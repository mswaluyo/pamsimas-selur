@extends('layouts.app')
@section('title', 'Edit Perangkat')

@section('content')
<div class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow">
    <h2 class="mb-4 font-semibold">Edit Perangkat <span class="font-mono text-sm text-slate-500">{{ $device->mac_address }}</span></h2>
    @include('devices._form')
</div>
@endsection
