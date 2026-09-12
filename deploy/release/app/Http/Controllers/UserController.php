<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    private function check(string $action = 'read'): void
    {
        Permission::abortUnlessCan(session('user.role', 'Viewer'), 'users', $action);
    }

    public function index()
    {
        $this->check();
        return view('users.index', ['users' => User::with('role')->orderBy('username')->get()]);
    }

    public function create()
    {
        $this->check('create');
        return view('users.create', ['roles' => Role::all()]);
    }

    public function store(Request $request)
    {
        $this->check('create');
        $data = $request->validate([
            'username' => 'required|string|max:50|unique:users,username',
            'password' => 'required|string|min:6|max:255',
            'full_name' => 'required|string|max:150',
            'role_id' => 'required|integer|exists:roles,id',
        ]);
        $data['password'] = Hash::make($data['password']);
        User::create($data);
        AdminLog::create(['user_id' => session('user.id', 0), 'action' => 'Tambah Pengguna', 'details' => $request->username]);
        return redirect()->route('users.index')->with('success', 'Pengguna ditambahkan.');
    }

    public function edit(int $id)
    {
        $this->check('update');
        return view('users.edit', ['user' => User::findOrFail($id), 'roles' => Role::all()]);
    }

    public function update(Request $request, int $id)
    {
        $this->check('update');
        $user = User::findOrFail($id);
        $data = $request->validate([
            'username' => "required|string|max:50|unique:users,username,{$id}",
            'password' => 'nullable|string|min:6|max:255',
            'full_name' => 'required|string|max:150',
            'role_id' => 'required|integer|exists:roles,id',
        ]);
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);
        return redirect()->route('users.index')->with('success', 'Pengguna diperbarui.');
    }

    public function destroy(int $id)
    {
        $this->check('delete');
        if ((int) $id === (int) session('user.id')) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }
        User::findOrFail($id)->delete();
        return back()->with('success', 'Pengguna dihapus.');
    }
}
