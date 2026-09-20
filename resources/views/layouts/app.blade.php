<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — PAMSIMAS SELUR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased">
<div class="flex min-h-screen">
    <!-- SIDEBAR -->
    <div id="sidebar-overlay" class="fixed inset-0 z-20 hidden bg-black/40 md:hidden"></div>
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 -translate-x-full bg-slate-900 text-slate-100 transition-transform md:translate-x-0">
        <div class="flex h-16 items-center gap-2 border-b border-slate-800 px-5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-sky-400 to-cyan-600 text-lg font-bold shadow-lg">💧</span>
            <div>
                <p class="text-sm font-bold leading-tight">PAMSIMAS</p>
                <p class="text-[11px] text-slate-400">Desa Selur</p>
            </div>
        </div>
        <nav class="space-y-1 overflow-y-auto p-3 text-sm" style="max-height: calc(100vh - 4rem)">
            @php $r = session('user.role'); @endphp
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('dashboard') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">📊 Dashboard</a>
            @if(in_array($r, ['Administrator','Operator']))
            <a href="{{ route('devices.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('devices.*') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">📡 Perangkat</a>
            <a href="{{ route('monitoring.overview') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('monitoring.*') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">🖥️ Monitoring</a>
            @endif
            @if(in_array($r, ['Administrator','Operator','Kasir']))
            <a href="{{ route('meter.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('meter.*') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">🔍 Kasir Meter</a>
            <a href="{{ route('payment.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('payment.*') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">💰 Pembayaran</a>
            <a href="{{ route('customers.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('customers.*') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">👥 Pelanggan</a>
            @endif
@if(in_array($r, ['Administrator','Operator']))
            <p class="pt-3 pl-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Pengaturan</p>
            <a href="{{ route('settings.tanks') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('settings.tanks') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">🛢️ Tangki</a>
            <a href="{{ route('settings.pumps') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('settings.pumps') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">⚙️ Pompa</a>
            <a href="{{ route('settings.sensors') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('settings.sensors') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">📶 Sensor</a>
            <a href="{{ route('settings.tariff') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('settings.tariff') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">💵 Tarif</a>
            <a href="{{ route('settings.display') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('settings.display') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">🎨 Tampilan</a>
            <a href="{{ route('templates.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('templates.*') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">🧩 Template Gauge</a>
            <p class="pt-3 pl-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Riwayat</p>
            <a href="{{ route('logs.pumps') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('logs.pumps') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">📜 Log Pompa</a>
            <a href="{{ route('logs.sensors') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('logs.sensors') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">📈 Log Sensor</a>
            <a href="{{ route('logs.events') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('logs.events') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">🔔 Log Event</a>
            <a href="{{ route('logs.admin') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->routeIs('logs.admin') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">🗂️ Log Admin</a>
            @endif
            @if($r === 'Administrator')
            <p class="pt-3 pl-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Sistem</p>
            <a href="/users" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors {{ request()->is('users*') ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white' }}">👤 Pengguna</a>
            @endif
        </nav>
    </aside>
        </nav>
    </aside>
<!-- MAIN -->
    <div class="flex min-h-screen flex-1 flex-col md:ml-64">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/90 px-5 backdrop-blur">
            <div class="flex items-center gap-3">
                <button id="sidebar-toggle" class="rounded-lg p-2 hover:bg-slate-100 md:hidden" aria-label="Menu">☰</button>
                <div>
                    <h1 class="text-lg font-bold">@yield('title')</h1>
                    <p class="text-[11px] text-slate-400">@yield('subtitle')</p>
                </div>
            </div>
            <div class="relative">
                <button id="user-menu-btn" class="flex items-center gap-3 rounded-lg p-1 pr-2 transition hover:bg-slate-100">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-sky-500 to-cyan-600 text-sm font-bold text-white">@php $fn = session('user.full_name'); $init = strtoupper(implode('', array_slice(preg_split('/\s+/u', (string)$fn), 0, 2))); echo $init ?: 'A'; @endphp</span>
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-semibold leading-tight">{{ session('user.full_name') }}</p>
                        <p class="text-[11px] text-slate-500">{{ session('user.role') }}</p>
                    </div>
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div id="user-menu" class="absolute right-0 mt-2 hidden w-56 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <p class="text-sm font-semibold">{{ session('user.full_name') }}</p>
                        <p class="text-xs text-slate-500">{{ session('user.role') }}</p>
                    </div>
                    @if($r === 'Administrator')
                    <a href="/users" class="block px-4 py-2 text-sm hover:bg-slate-50">👤 Kelola Pengguna</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">🚪 Keluar</button>
                    </form>
                </div>
            </div>
        </header>
<main class="flex-1 p-5">
            @if (session('success'))
                <div data-toast role="alert" class="mb-4 flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
                    <span class="text-lg">✅</span><span>{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div data-toast role="alert" class="mb-4 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm">
                    <span class="text-lg">⚠️</span><span>{{ session('error') }}</span>
                </div>
            @endif
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-white px-5 py-3 text-center text-xs text-slate-500">
            PAMSIMAS DESA SELUR © {{ date('Y') }} — Sistem Manajemen Air Desa
        </footer>
    </div>
</div>

<script>
    // Sidebar mobile toggle
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    });
    overlay?.addEventListener('click', () => {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    });

    // User dropdown
    const userMenu = document.getElementById('user-menu');
    const userBtn = document.getElementById('user-menu-btn');
    userBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        userMenu.classList.toggle('hidden');
    });
    document.addEventListener('click', (e) => {
        if (userMenu && !userMenu.classList.contains('hidden') && !userMenu.contains(e.target) && e.target !== userBtn) {
            userMenu.classList.add('hidden');
        }
    });

    // Auto-dismiss toast setelah 5 detik
    document.querySelectorAll('[data-toast]').forEach((toast) => {
        setTimeout(() => {
            toast.style.transition = 'opacity .4s ease, transform .4s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-8px)';
            setTimeout(() => toast.remove(), 400);
        }, 5000);
    });
</script>
@stack('scripts')
</body>
</html>