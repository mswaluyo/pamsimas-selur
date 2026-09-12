@extends('layouts.app')
@section('title', 'Tambah Pengguna')

@section('content')
<div class="mx-auto max-w-md rounded-xl bg-white p-6 shadow">
    <h2 class="mb-4 font-semibold">Tambah Pengguna</h2>
    <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
        @csrf
        <input type="text" name="username" required placeholder="Username" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input type="password" name="password" required minlength="6" placeholder="Password (min 6)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input type="text" name="full_name" required placeholder="Nama lengkap" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="role_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach($roles as $r)
            <option value="{{ $r->id }}">{{ $r->name }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-sky-600 px-5 py-2.5 font-semibold text-white hover:bg-sky-700">Simpan</button>
    </form>
</div>
@endsection