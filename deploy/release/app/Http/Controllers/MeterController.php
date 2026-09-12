<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use App\Models\Customer;
use App\Models\CustomerValidation;
use App\Models\IndicatorSetting;
use App\Models\MeterReading;
use App\Support\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeterController extends Controller
{
    private function check(string $action = 'read'): void
    {
        Permission::abortUnlessCan(session('user.role', 'Viewer'), 'meter', $action);
    }

    private function storagePath(): string
    {
        $env = config('services.meter_storage_path') ?? storage_path('app/meter_photos');
        if (!is_dir($env)) {
            @mkdir($env, 0775, true);
        }
        return $env;
    private function storagePath(): string
    {
        // Prioritas 1: Path dari config (bisa di-set via .env METER_STORAGE_PATH)
        $env = config('services.meter_storage_path');
        
        // Prioritas 2: Cek symlink ke SSD
        if (empty($env) || !is_dir($env)) {
            $ssdPath = base_path('meter_photos');
            if (is_dir($ssdPath)) {
                $env = $ssdPath;
            }
        }
        
        // Prioritas 3: Default storage lokal
        if (empty($env) || !is_dir($env)) {
            $env = storage_path('app/meter_photos');
        }
        
        // Buat direktori jika belum ada
        if (!is_dir($env)) {
            @mkdir($env, 0775, true);
        }
        
        return $env;
    }

    /**
     * Dapatkan path ke Python OCR executable
     * Mendukung Windows dan Linux secara otomatis
     */
    private function pythonPath(): string
    {
        // Prioritas 1: Path dari config (.env PYTHON_PATH)
        $python = config('services.python_path');
        if (!empty($python) && file_exists($python)) {
            return $python;
        }
        
        // Prioritas 2: Deteksi OS dan path default
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows: cek venv lokal
            $venvPython = base_path('venv\Scripts\python.exe');
            if (file_exists($venvPython)) {
                return $venvPython;
            }
        } else {
            // Linux/Armbian: cek berbagai lokasi
            $linuxPaths = [
                '/usr/local/bin/python_ocr',   // Custom symlink (aaPanel)
                base_path('venv/bin/python3'),  // Local venv
                '/usr/bin/python3',             // System Python
            ];
            foreach ($linuxPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
        }
        
        // Fallback: python3 di PATH
        return 'python3';
    }

    /**
     * Jalankan Python OCR script (smart_crop.py)
     */
    private function runPythonCrop(string $inputPath, string $outputPath): bool
    {
        $python = $this->pythonPath();
        $script = base_path(config('services.python_script_crop', 'public/smart_crop.py'));
        
        // Escape path untuk keamanan
        $cmd = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellarg($python),
            escapeshellarg($script),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            Log::error('Python OCR Crop failed', [
                'command' => $cmd,
                'output' => implode("\n", $output),
                'return_code' => $returnCode,
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * Jalankan Python OCR script (run_ocr.py)
     */
    private function runPythonOcr(string $imagePath): string
    {
        $python = $this->pythonPath();
        $script = base_path(config('services.python_script_ocr', 'public/run_ocr.py'));
        
        // Set environment untuk EasyOCR cache
        $easyocrPath = config('services.easyocr_module_path', storage_path('app/.easyocr/model'));
        $env = sprintf(
            'EASYOCR_MODULE_PATH=%s EASYOCR_USER_NETWORK_DIRECTORY=%s',
            escapeshellarg($easyocrPath),
            escapeshellarg(config('services.easyocr_user_network_directory', storage_path('app/.easyocr/user_network')))
        );
        
        $cmd = sprintf(
            '%s %s %s %s 2>&1',
            $env,
            escapeshellarg($python),
            escapeshellarg($script),
            escapeshellarg($imagePath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            Log::error('Python OCR Read failed', [
                'command' => $cmd,
                'output' => implode("\n", $output),
                'return_code' => $returnCode,
            ]);
            return '';
        }
        
        return trim(implode('', $output));
    }
    }

    public function index()
    {
        $this->check();
        $settings = IndicatorSetting::getSettings();
        $addresses = Customer::whereNotNull('address')->where('address', '!=', '')
            ->distinct()->orderBy('address')->pluck('address');

        return view('meter.index', [
            'customers' => Customer::orderBy('name')->get(),
            'default_period' => now()->format('Y-m'),
            'unique_addresses' => $addresses,
            'water_price' => (float) ($settings['water_price'] ?? 0),
            'admin_fee' => (float) ($settings['admin_fee'] ?? 0),
            'storage' => [
                'path' => $this->storagePath(),
                'writable' => is_writable($this->storagePath()),
            ],
        ]);
    }

    /**
     * JSON: antrean validasi (foto dari warga via WA OCR) + data yang sudah tercommit.
     */
    public function validationQueue()
    {
        $this->check();

        $pending = DB::table('customer_validations as s')
            ->leftJoin('customers as c', 's.lid', '=', 'c.lid')
            ->whereIn('s.status', ['MENUNGGU', 'KONFIRMASI', 'DIVALIDASI_WARGA'])
            ->select(
                's.session_id', 's.status', 's.angka_sementara as current_meter',
                's.foto_path', 's.updated_at as record_time',
                DB::raw("COALESCE(c.name, 'Belum Aktivasi') as name"),
                DB::raw("COALESCE(c.customer_id, '-') as customer_id"),
                'c.phone as wa_number',
                DB::raw("DATE_FORMAT(s.updated_at, '%Y-%m') as period"),
                DB::raw("'PENDING' as type")
            )
            ->orderByDesc('s.updated_at')
            ->get();

        $done = MeterReading::query()
            ->leftJoin('customers as c', 'meter_readings.customer_id', '=', 'c.customer_id')
            ->whereRaw("DATE_FORMAT(meter_readings.created_at, '%Y-%m') = ?", [now()->format('Y-m')])
            ->select(
                'meter_readings.id', 'meter_readings.customer_id', 'meter_readings.period',
                'meter_readings.current_meter', 'meter_readings.photo_path', 'meter_readings.created_at as record_time',
                'c.name', 'c.phone as wa_number',
                DB::raw("'DONE' as type")
            )
            ->orderByDesc('meter_readings.created_at')
            ->get();

        return response()->json(['status' => 'success', 'pending' => $pending, 'done' => $done]);
    }

    /**
     * Input manual langsung oleh operator (tanpa antrean foto).
     */
    public function store(Request $request)
    {
        $this->check('create');
        $data = $request->validate([
            'customer_id' => 'required|string|exists:customers,customer_id',
            'period' => 'required|regex:/^\d{4}-\d{2}$/',
            'current_meter' => 'required|numeric|min:0',
            'photo' => 'nullable|image|max:10240',
        ]);

        $result = $this->commitReading(
            customerId: $data['customer_id'],
            period: $data['period'],
            angka: (float) $data['current_meter'],
            photoPath: null,
            sessionId: null
        );

        if (!$result['ok']) {
            return back()->with('error', $result['message'])->withInput();
        }
        return back()->with('success', $result['message']);
    }

    /**
     * Validasi antrean foto oleh admin: simpan angka final + buat invoice.
     */
    public function adminValidate(Request $request)
    {
        $this->check('create');
        $data = $request->validate([
            'session_id' => 'required|string',
            'angka' => 'required|numeric',
        ]);

        $validation = CustomerValidation::whereIn('status', ['MENUNGGU', 'KONFIRMASI', 'DIVALIDASI_WARGA'])
            ->where('session_id', $data['session_id'])->first();

        if (!$validation) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan atau sudah diproses'], 404);
        }

        $customer = Customer::where('lid', $validation->lid)->first();
        if (!$customer) {
            return response()->json(['status' => 'error', 'message' => 'Pelanggan belum aktivasi (LID tidak dikenal)'], 404);
        }

        $period = optional($validation->updated_at)->format('Y-m') ?? now()->format('Y-m');
        $result = $this->commitReading($customer->customer_id, $period, (float) $data['angka'], $validation->foto_path, $validation->session_id);

        if (!$result['ok']) {
            return response()->json(['status' => 'error', 'message' => $result['message']], 422);
        }
        return response()->json(['status' => 'success', 'message' => $result['message']]);
    }

    /**
     * Validasi massal (angka OCR dipercaya langsung).
     */
    public function bulkValidate(Request $request)
    {
        $this->check('create');
        $sessions = (array) $request->input('sessions', []);
        $ok = 0; $fail = 0; $messages = [];

        foreach ($sessions as $sessionId) {
            $validation = CustomerValidation::whereIn('status', ['MENUNGGU', 'KONFIRMASI', 'DIVALIDASI_WARGA'])
                ->where('session_id', $sessionId)->first();
            if (!$validation || $validation->angka_sementara <= 0) { $fail++; continue; }

            $customer = Customer::where('lid', $validation->lid)->first();
            if (!$customer) { $fail++; continue; }

            $period = optional($validation->updated_at)->format('Y-m') ?? now()->format('Y-m');
            $result = $this->commitReading($customer->customer_id, $period, (float) $validation->angka_sementara, $validation->foto_path, $validation->session_id);
            $result['ok'] ? $ok++ : $fail++;
            if (!$result['ok']) $messages[] = $result['message'];
        }

        return response()->json(['status' => 'success', 'validated' => $ok, 'failed' => $fail, 'messages' => $messages]);
    }

    /**
     * Inti pencatatan: validasi angka vs bulan lalu, simpan reading, buat invoice,
     * kunci antrean (jika dari WA), kirim notifikasi WA.
     */
    private function commitReading(string $customerId, string $period, float $angka, ?string $photoPath, ?string $sessionId): array
    {
        $last = MeterReading::where('customer_id', $customerId)->where('period', '<', $period)
            ->orderByDesc('period')->value('current_meter');
        $lastMeter = (float) ($last ?? 0);

        if ($angka <= 0) {
            return ['ok' => false, 'message' => 'Angka meter tidak boleh 0. Masukkan angka manual dari foto.'];
        }
        if ($angka < $lastMeter) {
            return ['ok' => false, 'message' => "Angka meter ({$angka}) lebih kecil dari bulan lalu ({$lastMeter})"];
        }

        $usage = max(0, $angka - $lastMeter);

        $reading = MeterReading::updateOrCreate(
            ['customer_id' => $customerId, 'period' => $period],
            [
                'current_meter' => $angka,
                'photo_path' => $photoPath,
                'validated_by_user_id' => session('user.id'),
                'created_at' => now(),
            ]
        );

        $invoice = \App\Services\BillingService::createInvoice($reading->id, $customerId, $period, $usage);

        if ($sessionId) {
            CustomerValidation::where('session_id', $sessionId)
                ->update(['status' => 'SELESAI', 'angka_sementara' => (int) $angka]);
        }

        AdminLog::create([
            'user_id' => session('user.id', 0),
            'action' => 'Input Meter',
            'details' => "{$customerId} periode {$period}: {$angka} m3 (pemakaian {$usage} m3)",
        ]);

        $customer = Customer::where('customer_id', $customerId)->first();
        if ($customer && $customer->phone) {
            \App\Services\WhatsAppService::send($customer->phone,
                "*PAMSIMAS DESA SELUR*\nTagihan {$period} a/n {$customer->name} ({$customerId})\n"
                . "Pemakaian: {$usage} m3\nTotal: Rp " . number_format((float) $invoice->total_bill, 0, ',', '.')
                . "\n\nSilakan lakukan pembayaran ke kasir. Terima kasih.");
        }

        return ['ok' => true, 'message' => "Pencatatan {$customerId} tersimpan. Tagihan: Rp " . number_format((float) $invoice->total_bill, 0, ',', '.')];
    }

    public function delete(int $id)
    {
        $this->check('delete');
        $reading = MeterReading::findOrFail($id);
        \App\Models\Invoice::where('customer_id', $reading->customer_id)->where('period', $reading->period)->delete();
        $reading->delete();
        return back()->with('success', 'Pencatatan dihapus.');
    }

    public function deletePending(string $sessionId)
    {
        $this->check('delete');
        $v = CustomerValidation::find($sessionId);
        if ($v && $v->foto_path && is_file($this->storagePath() . '/' . $v->foto_path)) {
            @unlink($this->storagePath() . '/' . $v->foto_path);
        }
        CustomerValidation::where('session_id', $sessionId)->delete();
        return back()->with('success', 'Antrean dihapus.');
    }

    public function deleteAllPending()
    {
        $this->check('delete');
        $rows = CustomerValidation::whereIn('status', ['MENUNGGU', 'KONFIRMASI', 'DIVALIDASI_WARGA'])->get();
        foreach ($rows as $v) {
            if ($v->foto_path && is_file($this->storagePath() . '/' . $v->foto_path)) {
                @unlink($this->storagePath() . '/' . $v->foto_path);
            }
        }
        CustomerValidation::whereIn('status', ['MENUNGGU', 'KONFIRMASI', 'DIVALIDASI_WARGA'])->delete();
        return back()->with('success', 'Semua antrean dihapus.');
    }

    public function sendDirectMessage(Request $request)
    {
        $this->check('create');
        $data = $request->validate(['phone' => 'required|string', 'message' => 'required|string']);
        \App\Services\WhatsAppService::send($data['phone'], $data['message']);
        return back()->with('success', 'Pesan WhatsApp dikirim.');
    }

    public function getLastReading(string $customerId)
    {
        $reading = MeterReading::where('customer_id', $customerId)->orderByDesc('period')->first();
        return response()->json([
            'status' => 'success',
            'data' => $reading ? ['period' => $reading->period, 'current_meter' => (float) $reading->current_meter] : null,
        ]);
    }

    /**
     * Laporan status pencatatan seluruh pelanggan untuk satu periode.
     */
    public function meterReport(Request $request)
    {
        $this->check();
        $period = $request->query('period', now()->format('Y-m'));
        $address = $request->query('address', '');

        $query = Customer::query()
            ->leftJoin('meter_readings as mr', function ($j) use ($period) {
                $j->on('customers.customer_id', '=', 'mr.customer_id')->where('mr.period', $period);
            })
            ->leftJoin('customer_validations as cv', function ($j) use ($period) {
                $j->on('customers.lid', '=', 'cv.lid')
                  ->whereIn('cv.status', ['MENUNGGU', 'KONFIRMASI', 'DIVALIDASI_WARGA'])
                  ->whereRaw("DATE_FORMAT(cv.updated_at, '%Y-%m') = ?", [$period]);
            })
            ->select(
                'customers.customer_id', 'customers.name', 'customers.address', 'customers.phone',
                DB::raw("CASE WHEN mr.id IS NOT NULL THEN 'DONE' WHEN cv.session_id IS NOT NULL THEN 'PENDING' ELSE 'NONE' END as report_status"),
                'mr.current_meter', 'cv.angka_sementara as pending_meter'
            );

        if ($address !== '') {
            $query->where('customers.address', $address);
        }

        return response()->json(['status' => 'success', 'data' => $query->orderBy('customers.name')->get()]);
    }
}
