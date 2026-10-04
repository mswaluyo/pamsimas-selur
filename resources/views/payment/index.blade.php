@extends('layouts.app')
@section('title', 'Pembayaran Tagihan')

@section('content')
<div class="mb-4 flex flex-wrap items-center gap-3">
    <form method="GET" class="flex gap-2">
        <input type="text" name="period" value="{{ $period }}" pattern="\d{4}-\d{2}" placeholder="YYYY-MM" class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <button class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white">Tampilkan</button>
    </form>
    <div class="ml-auto flex gap-3 text-sm">
        <span class="rounded-lg bg-red-50 px-3 py-2 font-semibold text-red-700">Belum: Rp {{ number_format($totalBelum, 0, ',', '.') }}</span>
        <span class="rounded-lg bg-emerald-50 px-3 py-2 font-semibold text-emerald-700">Lunas: Rp {{ number_format($totalLunas, 0, ',', '.') }}</span>
    </div>
</div>

<div class="overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs uppercase text-slate-500">
                <th class="px-4 py-3">ID</th>
                <th class="px-4 py-3">Nama</th>
                <th class="px-4 py-3">Periode</th>
                <th class="px-4 py-3">Pemakaian</th>
                <th class="px-4 py-3">Total</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoices as $inv)
            <tr class="border-b hover:bg-slate-50">
                <td class="px-4 py-3 font-mono text-xs">{{ $inv->customer_id }}</td>
                <td class="px-4 py-3">{{ $inv->name }}</td>
                <td class="px-4 py-3">{{ $inv->period }}</td>
                <td class="px-4 py-3">{{ $inv->water_usage }} m³</td>
                <td class="px-4 py-3 font-semibold">Rp {{ number_format((float) $inv->total_bill, 0, ',', '.') }}</td>
                <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $inv->status_bayar === 'LUNAS' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ $inv->status_bayar }}</span>
                </td>
                <td class="px-4 py-3">
                    @if($inv->status_bayar === 'BELUM')
                    <button onclick="openPay({{ $inv->id }}, {{ (float) $inv->total_bill }})" class="rounded bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700">Proses Pembayaran</button>
                    @else
                    <span class="text-xs text-slate-400"><i class="fas fa-check"></i></span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Tidak ada tagihan periode {{ $period }}. Tagihan dibuat otomatis saat pencatatan meter.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

// Modal pembayaran
document.body.insertAdjacentHTML('beforeend', `
<div id="pay-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-sm rounded-xl bg-white p-6">
        <h3 class="mb-1 text-lg font-bold">Proses Pembayaran</h3>
        <p class="mb-4 text-sm text-slate-500">Tagihan <span id="pay-amount-label">Rp 0</span></p>
        <form id="pay-form" method="POST" action="{{ route('payment.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="invoice_id" id="pay-invoice-id">
            <div>
                <label class="mb-1 block text-sm font-medium">Uang Diterima (Rp)</label>
                <input type="number" step="0.01" min="0" name="amount" id="pay-amount" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Metode</label>
                <select name="method" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="TUNAI">Tunai</option>
                    <option value="TRANSFER">Transfer</option>
                </select>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closePay()" class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold">Batal</button>
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Proses</button>
            </div>
        </form>
    </div>
</div>`);

window.openPay = function (id, totalBill) {
    document.getElementById('pay-invoice-id').value = id;
    document.getElementById('pay-amount').value = totalBill;
    document.getElementById('pay-amount-label').textContent = 'Rp ' + Number(totalBill).toLocaleString('id-ID');
    document.getElementById('pay-modal').classList.remove('hidden');
    document.getElementById('pay-modal').classList.add('flex');
};

window.closePay = function () {
    document.getElementById('pay-modal').classList.add('hidden');
    document.getElementById('pay-modal').classList.remove('flex');
};
</script>
@endpush
