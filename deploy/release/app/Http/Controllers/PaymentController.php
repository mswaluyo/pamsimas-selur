<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\WhatsAppService;
use App\Support\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    private function check(string $action = 'read'): void
    {
        Permission::abortUnlessCan(session('user.role', 'Viewer'), 'payment', $action);
    }

    public function index()
    {
        $this->check();
        $period = request()->query('period', now()->format('Y-m'));
        $invoices = Invoice::query()
            ->leftJoin('customers as c', 'invoices.customer_id', '=', 'c.customer_id')
            ->where('invoices.period', $period)
            ->select('invoices.*', 'c.name', 'c.address', 'c.phone')
            ->orderBy('invoices.status_bayar')->orderBy('c.name')
            ->get();

        return view('payment.index', [
            'invoices' => $invoices,
            'period' => $period,
            'totalBelum' => (clone $invoices)->where('status_bayar', 'BELUM')->sum('total_bill'),
            'totalLunas' => (clone $invoices)->where('status_bayar', 'LUNAS')->sum('total_bill'),
        ]);
    }

    /**
     * JSON: daftar tagihan BELUM untuk pencarian kasir.
     */
    public function verifiedBills()
    {
        $this->check();
        $bills = Invoice::query()
            ->join('customers as c', 'invoices.customer_id', '=', 'c.customer_id')
            ->where('invoices.status_bayar', 'BELUM')
            ->select('invoices.id', 'invoices.customer_id', 'invoices.period', 'invoices.water_usage',
                     'invoices.water_price', 'invoices.admin_fee', 'invoices.total_bill', 'c.name', 'c.address')
            ->orderBy('invoices.period', 'desc')->orderBy('c.name')
            ->get();

        return response()->json(['status' => 'success', 'data' => $bills]);
    }

    public function getBill(int $id)
    {
        $this->check();
        $invoice = Invoice::with('payments')->findOrFail($id);
        $customer = Customer::where('customer_id', $invoice->customer_id)->first();

        return response()->json(['status' => 'success', 'data' => [
            'invoice' => $invoice,
            'customer' => $customer,
            'bill' => [
                'customer_id' => $invoice->customer_id,
                'name' => Customer::where('customer_id', $invoice->customer_id)->value('name'),
                'period' => $invoice->period,
                'water_usage' => (float) $invoice->water_usage,
                'water_price' => (float) $invoice->water_price,
                'admin_fee' => (float) $invoice->admin_fee,
                'total_bill' => (float) $invoice->total_bill,
                'status_bayar' => $invoice->status_bayar,
            ],
        ]]);
    }

    public function getReceipt(int $id)
    {
        $this->check();
        $payment = \App\Models\Payment::with('invoice')->findOrFail($id);
        $customer = Customer::where('customer_id', $payment->customer_id)->first();

        return response()->json(['status' => 'success', 'data' => [
            'receipt_number' => $payment->receipt_number,
            'customer_id' => $payment->customer_id,
            'name' => $customer?->name,
            'period' => $payment->period,
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'paid_at' => optional($payment->paid_at)->format('d-m-Y H:i'),
            'water_usage' => (float) $payment->invoice?->water_usage,
            'total_bill' => (float) $payment->invoice?->total_bill,
            'cashier' => session('user.full_name', '-'),
        ]]);
    }

    /**
     * Proses pembayaran tagihan: tandai LUNAS, catat payment, kirim kwitansi WA.
     */
    public function store(Request $request)
    {
        $this->check('create');
        $data = $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:TUNAI,TRANSFER',
        ]);

        $invoice = Invoice::findOrFail($data['invoice_id']);
        if ($invoice->status_bayar === 'LUNAS') {
            return back()->with('error', 'Tagihan ini sudah lunas.');
        }

        $payment = DB::transaction(function () use ($invoice, $data) {
            $payment = \App\Models\Payment::create([
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'period' => $invoice->period,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'receipt_number' => 'RCP-' . date('YmdHis') . '-' . substr(uniqid(), -4),
                'user_id' => session('user.id'),
                'paid_at' => now(),
            ]);

            $invoice->status_bayar = 'LUNAS';
            $invoice->save();

            return $payment;
        });

        AdminLog::create([
            'user_id' => session('user.id', 0),
            'action' => 'Pembayaran',
            'details' => "{$invoice->customer_id} {$invoice->period}: Rp " . number_format((float) $data['amount'], 0, ',', '.') . " ({$data['method']})",
        ]);

        $customer = Customer::where('customer_id', $invoice->customer_id)->first();
        if ($customer && $customer->phone) {
            $usage = (float) $invoice->water_usage;
            WhatsAppService::send($customer->phone,
                "*PAMSIMAS DESA SELUR*\nKWITANSI PEMBAYARAN\nNo: {$payment->receipt_number}\n\n"
                . "A/n: {$customer->name} ({$customer->customer_id})\nPeriode: {$invoice->period}\n"
                . "Pemakaian: {$usage} m3\nTotal: Rp " . number_format((float) $invoice->total_bill, 0, ',', '.') . "\n"
                . "Dibayar: Rp " . number_format((float) $data['amount'], 0, ',', '.') . " ({$data['method']})\n"
                . "Status: LUNAS\n\nTerima kasih.");
        }

        return back()->with('success', "Pembayaran diproses. No. Kwitansi: {$payment->receipt_number}");
    }

    /**
     * Kirim kwitansi WA untuk satu pembayaran (dipanggil dari halaman pembayaran).
     */
    public function sendReceiptWA(int $id)
    {
        $this->check('create');
        $payment = Payment::findOrFail($id);
        $customer = Customer::where('customer_id', $payment->customer_id)->first();
        if (!$customer || !$customer->phone) {
            return back()->with('error', 'Pelanggan tidak memiliki nomor WhatsApp.');
        }

        WhatsAppService::send($customer->phone,
            "*PAMSIMAS DESA SELUR*\nKWITANSI PEMBAYARAN\nNo: {$payment->receipt_number}\n"
            . "A/n: {$customer->name} ({$customer->customer_id})\nPeriode: {$payment->period}\n"
            . "Jumlah: Rp " . number_format((float) $payment->amount, 0, ',', '.') . "\nStatus: LUNAS\nTerima kasih.");

        return back()->with('success', 'Kwitansi dikirim via WhatsApp.');
    }
}
