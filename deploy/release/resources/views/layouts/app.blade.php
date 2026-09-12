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
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 -translate-x-full bg-slate-900 text-slate-100 transition-transform md:translate-x-0">
        <div class="flex h-16 items-center gap-2 border-b border-slate-800 px-5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-500 text-lg font-bold">💧</span>
            <div>
                <p class="text-sm font-bold leading-tight">PAMSIMAS</p>
                <p class="text-[11px] text-slate-400">Desa Selur</p>
            </div>
        </div>
        <nav class="space-y-1 overflow-y-auto p-3 text-sm" style="max-height: calc(100vh - 4rem)">
            @php $r = session('user.role'); @endphp
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('dashboard') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">📊 Dashboard</a>
            @if(in_array($r, ['Administrator','Operator']))
            <a href="{{ route('devices.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('devices.*') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">📡 Perangkat</a>
            <a href="{{ route('monitoring.overview') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('monitoring.*') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">🖥️ Monitoring</a>
            @endif
            @if(in_array($r, ['Administrator','Operator','Kasir']))
            <a href="{{ route('meter.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('meter.*') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">🔍 Kasir Meter</a>
            <a href="{{ route('payment.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('payment.*') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">💰 Pembayaran</a>
            <a href="{{ route('customers.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('customers.*') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">👥 Pelanggan</a>
            @endif
            @if(in_array($r, ['Administrator','Operator']))
            <p class="pt-3 pl-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Pengaturan</p>
            <a href="{{ route('settings.tanks') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('settings.tanks') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">🛢️ Tangki</a>
            <a href="{{ route('settings.pumps') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('settings.pumps') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">⚙️ Pompa</a>
            <a href="{{ route('settings.sensors') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('settings.sensors') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">📶 Sensor</a>
            <a href="{{ route('settings.tariff') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('settings.tariff') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">💵 Tarif</a>
            <a href="{{ route('settings.display') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('settings.display') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">🎨 Tampilan</a>
            <a href="{{ route('templates.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('templates.*') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">🧩 Template Gauge</a>
            <p class="pt-3 pl-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Riwayat</p>
            <a href="{{ route('logs.pumps') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('logs.pumps') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">📜 Log Pompa</a>
            <a href="{{ route('logs.sensors') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('logs.sensors') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">📈 Log Sensor</a>
            <a href="{{ route('logs.events') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('logs.events') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">🔔 Log Event</a>
            <a href="{{ route('logs.admin') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('logs.admin') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">🗂️ Log Admin</a>
            @endif
            @if($r === 'Administrator')
            <p class="pt-3 pl-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Sistem</p>
            <a href="/users" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->is('users*') ? 'bg-sky-600' : 'hover:bg-slate-800' }}">👤 Pengguna</a>
            @endif
        </nav>
    </aside>

    <!-- MAIN -->
    <div class="flex min-h-screen flex-1 flex-col md:ml-64">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-5">
            <div class="flex items-center gap-3">
                <button id="sidebar-toggle" class="rounded-lg p-2 hover:bg-slate-100 md:hidden">☰</button>
                <h1 class="text-lg font-bold">@yield('title')</h1>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <p class="text-sm font-semibold">{{ session('user.full_name') }}</p>
                    <p class="text-[11px] text-slate-500">{{ session('user.role') }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-lg bg-slate-200 px-3 py-2 text-sm font-medium hover:bg-red-100 hover:text-red-700">Keluar</button>
                </form>
            </div>
        </header>

        <main class="flex-1 p-5">
            @if (session('success'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-white px-5 py-3 text-center text-xs text-slate-500">
            PAMSIMAS DESA SELUR © {{ date('Y') }} — Sistem Manajemen Air Desa
        </footer>
    </div>
</div>

<script>
    document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('-translate-x-full');
    });
</script>
@stack('scripts')
</body>
</html>
