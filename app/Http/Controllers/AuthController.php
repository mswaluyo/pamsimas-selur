<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use App\Models\EventLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session('user')) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string|max:50',
            'password' => 'required|string|max:255',
        ]);

        // Rate limiting: maks 5 percobaan gagal per menit per IP
        $key = 'login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'Terlalu banyak percobaan. Coba lagi dalam satu menit.');
        }

        $user = User::with('role')->where('username', $credentials['username'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            EventLog::create([
                'device_id' => 0,
                'event_type' => 'Security Alert',
                'message' => "Percobaan login gagal: {$credentials['username']} dari IP {$request->ip()}",
                'event_time' => now(),
            ]);
            return back()->with('error', 'Username atau password salah.')->onlyInput('username');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        session([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'role' => $user->role->name,
            ],
        ]);

        EventLog::create([
            'device_id' => 0,
            'event_type' => 'Security',
            'message' => "Login berhasil: {$user->username} (Role: {$user->role->name})",
            'event_time' => now(),
        ]);
        if ($user->isAdmin()) {
            AdminLog::create(['user_id' => $user->id, 'action' => 'Login', 'details' => "Administrator login dari IP {$request->ip()}"]);
        }

        if ($request->boolean('remember')) {
            config(['session.lifetime' => 60 * 24 * 30]);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        if ($u = session('user')) {
            EventLog::create([
                'device_id' => 0,
                'event_type' => 'Security',
                'message' => "Logout: {$u['full_name']}",
                'event_time' => now(),
            ]);
            if (($u['role'] ?? '') === 'Administrator') {
                AdminLog::create(['user_id' => $u['id'], 'action' => 'Logout', 'details' => 'Administrator logout']);
            }
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
