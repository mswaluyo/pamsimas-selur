<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <title>@yield('title', 'Dashboard') — PAMSIMAS SELUR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased">
<div class="flex min-h-screen">
    <!-- SIDEBAR -->
    <div id="sidebar-overlay" class="fixed inset-0 z-20 hidden bg-black/40 md:hidden"></div>
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 -translate-x-full bg-slate-900 text-slate-100 transition-transform md:translate-x-0">
        <div class="flex h-16 items-center gap-2 border-b border-slate-800 px-5">
            <span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg bg-gradient-to-br from-sky-400 to-cyan-600 shadow-lg">
                <img src="/img/logo.png" alt="Logo PAMSIMAS" class="h-full w-full object-cover">
            </span>
            <div>
                <p class="text-sm font-bold leading-tight">PAMSIMAS</p>
                <p class="text-[11px] text-slate-400">Desa Selur</p>
            </div>
        </div>
        <nav class="space-y-1 overflow-y-auto p-3 text-sm" style="max-height: calc(100vh - 4rem)">
            @php
                $r = session('user.role');
                $navSection = function ($label) {
                    return '<p class="pt-3 pb-1 pl-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">' . $label . '</p>';
                };
                $navItem = function ($href, $active, $label, $badge = null) {
                    $cls = $active ? 'bg-sky-600 font-semibold text-white shadow' : 'hover:bg-slate-800 hover:text-white';
                    $badgeHtml = $badge ? '<span class="ml-auto rounded-full bg-amber-400 px-2 text-xs font-bold text-slate-900">' . $badge . '</span>' : '';
                    return '<a href="' . $href . '" class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors ' . $cls . '">' . $label . $badgeHtml . '</a>';
                };
            @endphp

            {!! $navSection('Utama') !!}
            {!! $navItem(route('dashboard'), request()->routeIs('dashboard'), '📊 Dashboard') !!}

            @if(in_array($r, ['Administrator','Operator']))
            {!! $navSection('Manajemen IoT') !!}
            {!! $navItem(route('devices.index'), request()->routeIs('devices.index', 'devices.show', 'devices.edit'), '📡 Perangkat') !!}
            {!! $navItem(route('devices.detected'), request()->routeIs('devices.detected', 'devices.create'), '🔎 Perangkat Terdeteksi', ($detectedCount ?? 0) > 0 ? $detectedCount : null) !!}
            {!! $navItem(route('monitoring.overview'), request()->routeIs('monitoring.*'), '🖥️ Monitoring') !!}
            @endif

            @if(in_array($r, ['Administrator','Operator','Kasir']))
            {!! $navSection('Layanan Warga') !!}
            {!! $navItem(route('meter.index'), request()->routeIs('meter.*'), '🔍 Kasir Meter') !!}
            {!! $navItem(route('payment.index'), request()->routeIs('payment.*'), '💰 Pembayaran') !!}
            {!! $navItem(route('customers.index'), request()->routeIs('customers.*'), '👥 Pelanggan') !!}
            @endif

            @if(in_array($r, ['Administrator','Operator']))
            {!! $navSection('Pengaturan') !!}
            {!! $navItem(route('settings.tanks'), request()->routeIs('settings.tanks'), '🛢️ Tangki') !!}
            {!! $navItem(route('settings.pumps'), request()->routeIs('settings.pumps'), '⚙️ Pompa') !!}
            {!! $navItem(route('settings.sensors'), request()->routeIs('settings.sensors'), '📶 Sensor') !!}
            {!! $navItem(route('settings.tariff'), request()->routeIs('settings.tariff'), '💵 Tarif') !!}
            {!! $navItem(route('settings.display'), request()->routeIs('settings.display'), '🎨 Tampilan') !!}
            {!! $navItem(route('templates.index'), request()->routeIs('templates.*'), '🧩 Template Gauge') !!}

            {!! $navSection('Riwayat') !!}
            {!! $navItem(route('logs.pumps'), request()->routeIs('logs.pumps'), '📜 Log Pompa') !!}
            {!! $navItem(route('logs.sensors'), request()->routeIs('logs.sensors'), '📈 Log Sensor') !!}
            {!! $navItem(route('logs.events'), request()->routeIs('logs.events'), '🔔 Log Event') !!}
            {!! $navItem(route('logs.admin'), request()->routeIs('logs.admin'), '🗂️ Log Admin') !!}
            @endif

            @if($r === 'Administrator')
            {!! $navSection('Sistem') !!}
            {!! $navItem('/users', request()->is('users*'), '👤 Pengguna') !!}
            @endif
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
                    <span title="{{ session('user.full_name') ?: session('user.username') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-sky-500 to-cyan-600 text-sm font-bold text-white ring-2 ring-sky-200">@php
                        $fn    = trim((string) session('user.full_name'));
                        $usr   = trim((string) session('user.username'));
                        $base  = $fn !== '' ? $fn : $usr;
                        $parts = preg_split('/\s+/u', $base, -1, PREG_SPLIT_NO_EMPTY);
                        if (count($parts) >= 2) {
                            $init = mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1);
                        } elseif (count($parts) === 1) {
                            $init = mb_substr($parts[0], 0, 1);
                        } else {
                            $init = 'U';
                        }
                        echo mb_strtoupper($init);
                    @endphp</span>
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