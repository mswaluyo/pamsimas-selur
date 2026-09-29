@extends('layouts.app')
@section('title', 'Edit Pengguna')

@section('content')
<div class="mx-auto max-w-md rounded-xl bg-white p-6 shadow">
    <h2 class="mb-4 font-semibold">Edit Pengguna: {{ $user->username }}</h2>
    <form method="POST" action="{{ route('users.update', $user->id) }}" class="space-y-4">
        @csrf
        <div>
            <label for="username" class="mb-1 block text-sm font-medium">Username</label>
            <input type="text" id="username" name="username" required value="{{ $user->username }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Password Baru</label>
            <input type="password" id="password" name="password" minlength="6" placeholder="kosongkan jika tidak diganti" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="full_name" class="mb-1 block text-sm font-medium">Nama Lengkap</label>
            <input type="text" id="full_name" name="full_name" required value="{{ $user->full_name }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="role_id" class="mb-1 block text-sm font-medium">Role / Hak Akses</label>
            <select id="role_id" name="role_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach($roles as $r)
                <option value="{{ $r->id }}" @if($user->role_id == $r->id) selected @endif>{{ $r->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-lg bg-sky-600 px-5 py-2.5 font-semibold text-white hover:bg-sky-700">Simpan Perubahan</button>
    </form>
</div>
@endsection