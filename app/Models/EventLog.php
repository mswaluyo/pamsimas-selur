<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['device_id', 'event_type', 'message', 'event_time'];

    protected $casts = [
        'event_time' => 'datetime',
    ];

    /** Relasi ke perangkat (dipakai oleh LogApiController::with('device')). */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Catat kejadian "Koneksi terputus" (IDEMPOTEN) untuk satu perangkat.
     *
     * Jangkar = kontak terakhir (last_update): selama perangkat tetap offline,
     * jangkar tidak berubah sehingga event tidak ditulis dobel walau fungsi
     * ini dipanggu berulang (dari halaman detail & dari middleware saat
     * perangkat kembali terhubung). Dipakai Task #42 — log koneksi.
     */
    public static function logDisconnect(int $deviceId, \DateTimeInterface $lastContact): bool
    {
        $anchor = \Carbon\Carbon::parse($lastContact)->format('Y-m-d H:i:s');
        $exists = static::where('device_id', $deviceId)
            ->where('event_type', 'Koneksi')
            ->where('event_time', $anchor)
            ->where('message', 'like', 'Koneksi terputus%')
            ->exists();
        if ($exists) {
            return false;
        }
        static::create([
            'device_id' => $deviceId,
            'event_type' => 'Koneksi',
            'message' => 'Koneksi terputus — perangkat offline',
            'event_time' => $anchor,
        ]);
        return true;
    }
}
