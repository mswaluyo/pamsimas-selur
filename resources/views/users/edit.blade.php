@extends('layouts.app')
@section('title', 'Edit Pengguna')

@section('content')
<div class="mx-auto max-w-md rounded-xl bg-white p-6 shadow">
    <h2 class="mb-4 font-semibold">Edit Pengguna: {{ $user->username }}</h2>
    <form method="POST" action="{{ route('users.update', $user->id) }}" class="space-y-4">
        @csrf
        <input type="text" name="username" required value="{{ $user->username }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input type="password" name="password" minlength="6" placeholder="Password baru (kosongkan jika tidak diganti)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input type="text" name="full_name" required value="{{ $user->full_name }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="role_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach($roles as $r)
            <option value="{{ $r->id }}" @if($user->role_id == $r->id) selected @endif>{{ $r->name }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-sky-600 px-5 py-2.5 font-semibold text-white hover:bg-sky-700">Simpan Perubahan</button>
    </form>
</div>
@endsection