<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <title>Login — PAMSIMAS SELUR</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gradient-to-br from-sky-900 via-slate-900 to-sky-950 p-4 font-sans">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <span class="mb-2 inline-flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl bg-sky-500 shadow">
                <img src="/img/logo.png" alt="Logo PAMSIMAS" class="h-full w-full object-cover">
            </span>
            <h1 class="text-2xl font-bold text-white">PAMSIMAS SELUR</h1>
            <p class="text-sm text-sky-300">Sistem Manajemen Air Desa</p>
        </div>

        <div class="rounded-2xl bg-white p-8 shadow-2xl">
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Username</label>
                    <input type="text" name="username" value="{{ old('username') }}" required autofocus
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                    <input type="password" name="password" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="rounded border-slate-300">
                    Ingat saya (30 hari)
                </label>
                <button type="submit" class="w-full rounded-lg bg-sky-600 py-2.5 font-semibold text-white hover:bg-sky-700">
                    Masuk
                </button>
            </form>
        </div>
        <p class="mt-4 text-center text-xs text-slate-400">© {{ date('Y') }} PAMSIMAS Desa Selur</p>
    </div>
</body>
</html>
