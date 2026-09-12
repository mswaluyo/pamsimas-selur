<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use App\Models\Customer;
use App\Services\WhatsAppService;
use App\Support\Permission;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    private function check(string $action = 'read'): void
    {
        Permission::abortUnlessCan(session('user.role', 'Viewer'), 'customers', $action);
    }

    public function index(Request $request)
    {
        $this->check();
        $query = Customer::query();
        if ($search = $request->query('q')) {
            $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('customer_id', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%"));
        }
        return view('customers.index', ['customers' => $query->orderBy('name')->paginate(20)->withQueryString()]);
    }

    public function create()
    {
        $this->check('create');
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $this->check('create');
        Customer::create($this->validated($request));
        AdminLog::create(['user_id' => session('user.id', 0), 'action' => 'Tambah Pelanggan', 'details' => $request->input('customer_id')]);
        return redirect()->route('customers.index')->with('success', 'Pelanggan ditambahkan. Warga wajib mengirim pesan AKTIVASI-{ID} ke nomor server agar fitur kirim foto aktif.');
    }

    public function edit(int $id)
    {
        $this->check('update');
        return view('customers.edit', ['customer' => Customer::findOrFail($id)]);
    }

    public function update(Request $request, int $id)
    {
        $this->check('update');
        Customer::findOrFail($id)->update($this->validated($request, ignoreId: $id));
        return redirect()->route('customers.index')->with('success', 'Pelanggan diperbarui.');
    }

    public function destroy(int $id)
    {
        $this->check('delete');
        Customer::findOrFail($id)->delete();
        return back()->with('success', 'Pelanggan dihapus.');
    }

    public function export(): StreamedResponse
    {
        $this->check();
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['customer_id', 'name', 'address', 'phone', 'lid']);
            Customer::chunk(500, fn ($rows) => $rows->each(fn ($c) => fputcsv($out, [$c->customer_id, $c->name, $c->address, $c->phone, $c->lid])));
            fclose($out);
        }, 'pelanggan_' . date('Ymd') . '.csv');
    }

    public function import(Request $request)
    {
        $this->check('create');
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $count = 0;
        $header = fgetcsv($handle);
        while ($row = fgetcsv($handle)) {
            if (count($row) < 2) continue;
            Customer::updateOrCreate(
                ['customer_id' => trim($row[0])],
                [
                    'name' => trim($row[1]),
                    'address' => $row[2] ?? null,
                    'phone' => $row[3] ?? null,
                    'lid' => $row[4] ?? null,
                ]
            );
            $count++;
        }
        fclose($handle);
        return back()->with('success', "Import selesai: {$count} baris diproses.");
    }

    /**
     * Broadcast permintaan foto meteran ke semua warga yang belum lapor bulan ini.
     */
    public function broadcastRequest()
    {
        $this->check('create');
        $period = now()->format('Y-m');
        $customers = Customer::whereNotNull('phone')->where('phone', '!=', '')->get();
        $sent = 0;
        foreach ($customers as $c) {
            $already = \App\Models\MeterReading::where('customer_id', $c->customer_id)->where('period', $period)->exists()
                || \App\Models\CustomerValidation::where('phone', $c->phone)
                    ->whereIn('status', ['MENUNGGU', 'KONFIRMASI', 'DIVALIDASI_WARGA'])
                    ->exists();
            if ($already) continue;
            WhatsAppService::send($c->phone,
                "*PAMSIMAS DESA SELUR*\nYth Bpk/Ibu {$c->name} ({$c->customer_id}),\n\nMohon kirim FOTO meteran air Anda bulan ini.\nTerima kasih.");
            $sent++;
        }
        AdminLog::create(['user_id' => session('user.id', 0), 'action' => 'Broadcast Meter', 'details' => "Permintaan foto dikirim ke {$sent} pelanggan"]);
        return back()->with('success', "Permintaan foto dikirim ke {$sent} pelanggan.");
    }

    public function broadcastHistory()
    {
        $this->check();
        return view('customers.broadcast_history', [
            'logs' => AdminLog::where('action', 'Broadcast Meter')->orderByDesc('created_at')->paginate(20),
        ]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $unique = $ignoreId ? "unique:customers,customer_id,{$ignoreId}" : 'unique:customers,customer_id';
        return $request->validate([
            'customer_id' => "required|string|max:50|{$unique}",
            'name' => 'required|string|max:150',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'lid' => 'nullable|string|max:50',
        ]);
    }
}
