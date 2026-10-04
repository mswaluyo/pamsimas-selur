<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <title>@yield('title', 'Dashboard') — PAMSIMAS SELUR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Font Awesome 6.4.2 — tema ikon sama dengan sistem lama
         (backup_pamsimas/app/Views/layouts/main.php:34) --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Ikon navigasi: lebar tetap + rata tengah agar label menu sejajar */
        #sidebar nav a > i.fas, #user-menu a > i.fas, #user-menu button > i.fas { width: 1.15em; text-align: center; flex: none; }

        /* Cegah halaman melebar di layar sempit (akar masalah, TODO §7.25).
           Pembungkus `flex-1` (baris 88) dan <main> adalah flex item yang min-width-nya `auto`,
           sehingga dipaksa selebar min-content anaknya — baris 7 kartu statistik = 566px, jadi
           seluruh halaman jadi 606px dan muncul scroll horizontal di HP.
           Dengan min-width:0 lebarnya mengikuti viewport; baris statistik tetap bisa di-scroll
           sendiri karena sudah memakai overflow-x:auto. Desktop tidak terpengaruh. */
        .flex.min-h-screen.flex-1, main { min-width: 0; }

        /* Mode ringkas untuk HP (TODO §7.29) — mis. Redmi Note 11 & HP 5,5–6,7":
           kerangka (topbar, jarak luar, footer) diperkecil supaya konten tidak terasa
           "membesar" dan layar yang sempit terisi lebih efektif. */
        @media (max-width:480px) {
            #app-header { height: 48px; padding-left: 10px; padding-right: 10px; }
            #app-main { padding: 10px; }
            #app-footer { padding: 7px 10px; font-size: .66rem; }
        }
    </style>
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
            {!! $navItem(route('dashboard'), request()->routeIs('dashboard'), '<i class="fas fa-tachometer-alt"></i> Dashboard') !!}

            @if(in_array($r, ['Administrator','Operator']))
            {!! $navSection('Manajemen IoT') !!}
            {{-- Menu "Perangkat Terdeteksi" dihapus dari sidebar (permintaan operator, 4 Okt 2026):
                 daftarnya sudah tersedia di halaman /devices (bagian "Perangkat Terdeteksi Otomatis").
                 Rute devices.detected & devices.create tetap disorot oleh menu Perangkat. --}}
            {!! $navItem(route('devices.index'), request()->routeIs('devices.index', 'devices.show', 'devices.edit', 'devices.detected', 'devices.create'), '<i class="fas fa-microchip"></i> Perangkat') !!}
            {!! $navItem(route('monitoring.overview'), request()->routeIs('monitoring.*'), '<i class="fas fa-server"></i> Monitoring') !!}
            @endif

            @if(in_array($r, ['Administrator','Operator','Kasir']))
            {!! $navSection('Layanan Warga') !!}
            {!! $navItem(route('meter.index'), request()->routeIs('meter.*'), '<i class="fas fa-file-invoice-dollar"></i> Kasir Meter') !!}
            {!! $navItem(route('payment.index'), request()->routeIs('payment.*'), '<i class="fas fa-money-bill-wave"></i> Pembayaran') !!}
            {!! $navItem(route('customers.index'), request()->routeIs('customers.*'), '<i class="fas fa-address-book"></i> Pelanggan') !!}
            @endif

            @if(in_array($r, ['Administrator','Operator']))
            {!! $navSection('Pengaturan') !!}
            {!! $navItem(route('settings.tanks'), request()->routeIs('settings.tanks'), '<i class="fas fa-database"></i> Tangki') !!}
            {!! $navItem(route('settings.pumps'), request()->routeIs('settings.pumps'), '<i class="fas fa-fan"></i> Pompa') !!}
            {!! $navItem(route('settings.sensors'), request()->routeIs('settings.sensors'), '<i class="fas fa-satellite-dish"></i> Sensor') !!}
            {!! $navItem(route('settings.tariff'), request()->routeIs('settings.tariff'), '<i class="fas fa-hand-holding-usd"></i> Tarif') !!}
            {!! $navItem(route('settings.display'), request()->routeIs('settings.display'), '<i class="fas fa-palette"></i> Tampilan') !!}
            {!! $navItem(route('templates.index'), request()->routeIs('templates.*'), '<i class="fas fa-magic"></i> Template Gauge') !!}

            {!! $navSection('Riwayat') !!}
            {!! $navItem(route('logs.pumps'), request()->routeIs('logs.pumps'), '<i class="fas fa-history"></i> Log Pompa') !!}
            {!! $navItem(route('logs.sensors'), request()->routeIs('logs.sensors'), '<i class="fas fa-chart-line"></i> Log Sensor') !!}
            {!! $navItem(route('logs.events'), request()->routeIs('logs.events'), '<i class="fas fa-list-check"></i> Log Event') !!}
            {!! $navItem(route('logs.admin'), request()->routeIs('logs.admin'), '<i class="fas fa-shield-alt"></i> Log Admin') !!}
            @endif

            @if($r === 'Administrator')
            {!! $navSection('Sistem') !!}
            {!! $navItem('/users', request()->is('users*'), '<i class="fas fa-users-cog"></i> Pengguna') !!}
            @endif
        </nav>
    </aside>
<!-- MAIN -->
    <div class="flex min-h-screen flex-1 flex-col md:ml-64">
        <header id="app-header" class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/90 px-5 backdrop-blur">
            <div class="flex items-center gap-3">
                <button id="sidebar-toggle" class="rounded-lg p-2 hover:bg-slate-100 md:hidden" aria-label="Menu"><i class="fas fa-bars"></i></button>
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
                    <a href="/users" class="block px-4 py-2 text-sm hover:bg-slate-50"><i class="fas fa-users-cog"></i> Kelola Pengguna</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"><i class="fas fa-sign-out-alt"></i> Keluar</button>
                    </form>
                </div>
            </div>
        </header>
<main id="app-main" class="flex-1 p-5">
            @if (session('success'))
                <div data-toast role="alert" class="mb-4 flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
                    <i class="fas fa-check-circle"></i><span>{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div data-toast role="alert" class="mb-4 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm">
                    <i class="fas fa-exclamation-triangle"></i><span>{{ session('error') }}</span>
                </div>
            @endif
            @yield('content')
        </main>

        <footer id="app-footer" class="border-t border-slate-200 bg-white px-5 py-3 text-center text-xs text-slate-500">
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