@extends('layouts.app')
@section('title', 'Manajemen Pengguna')

@section('content')
<div class="mb-4 flex justify-end">
    <a href="{{ route('users.create') }}" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700"><i class="fas fa-plus mr-1"></i>Tambah Pengguna</a>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3">Username</th>
                <th class="px-4 py-3">Nama Lengkap</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $u)
            <tr class="border-b hover:bg-slate-50">
                <td class="px-4 py-3 font-medium">{{ $u->username }}</td>
                <td class="px-4 py-3">{{ $u->full_name }}</td>
                <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $u->role?->name }}</span></td>
                <td class="px-4 py-3">
                    <div class="flex gap-1">
                        <a href="{{ route('users.edit', $u->id) }}" class="rounded bg-sky-100 px-2 py-1 text-xs text-sky-700 hover:bg-sky-200"><i class="fas fa-pen mr-1"></i>Edit</a>
                        @if($u->id !== session('user.id'))
                        <form method="POST" action="{{ route('users.delete', $u->id) }}" onsubmit="return confirm('Hapus pengguna {{ $u->username }}?')">
                            @csrf
                            <button class="rounded bg-red-100 px-2 py-1 text-xs text-red-700 hover:bg-red-200"><i class="fas fa-trash-can mr-1"></i>Hapus</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection