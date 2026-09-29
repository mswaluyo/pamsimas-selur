@extends('layouts.app')
@section('title', 'Tambah Pengguna')

@section('content')
<div class="mx-auto max-w-md rounded-xl bg-white p-6 shadow">
    <h2 class="mb-4 font-semibold">Tambah Pengguna</h2>
    <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="username" class="mb-1 block text-sm font-medium">Username</label>
            <input type="text" id="username" name="username" required placeholder="cth: operator1" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Password</label>
            <input type="password" id="password" name="password" required minlength="6" placeholder="minimal 6 karakter" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="full_name" class="mb-1 block text-sm font-medium">Nama Lengkap</label>
            <input type="text" id="full_name" name="full_name" required placeholder="cth: Budi Santoso" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="role_id" class="mb-1 block text-sm font-medium">Role / Hak Akses</label>
            <select id="role_id" name="role_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach($roles as $r)
                <option value="{{ $r->id }}">{{ $r->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-lg bg-sky-600 px-5 py-2.5 font-semibold text-white hover:bg-sky-700">Simpan</button>
    </form>
</div>
@endsection