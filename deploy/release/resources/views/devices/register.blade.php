@extends('layouts.app')
@section('title', 'Daftarkan Perangkat')

@section('content')
<div class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow">
    <h2 class="mb-4 font-semibold">Registrasi Perangkat Baru</h2>
    @include('devices._form')
</div>
@endsection
