<?php

namespace App\Support;

use App\Models\PumpLog;
use Illuminate\Support\Collection;

/**
 * Durasi NYALA / MATI pompa dihitung dari transisi `pump_logs` — **tanpa mengubah database**
 * (kolom `pump_logs.duration_seconds` tidak pernah diisi oleh firmware maupun server,
 * jadi nilainya selalu 0).
 *
 * Cara hitung: untuk setiap transisi, durasi = selisih waktu dengan transisi SEBELUMNYA pada
 * perangkat yang sama, dan label mengikuti keadaan yang baru berakhir:
 *   - transisi ON  ⇒ durasi MATI (istirahat) sebelum pompa menyala;
 *   - transisi OFF ⇒ durasi NYALA sebelum pompa dimatikan.
 *
 * Dipakai oleh halaman detail perangkat (`devices/show.blade.php`, kejadian Pump) dan
 * halaman Riwayat Log Pompa (`logs/pumps.blade.php`).
 */
class PumpDuration
{
    /** Format detik menjadi 'HH:MM:SS' (ditambah 'Xd ' bila lebih dari sehari). */
    public static function format(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $days = intdiv($seconds, 86400);

        return ($days > 0 ? $days . 'h ' : '')
            . sprintf('%02d:%02d:%02d', intdiv($seconds % 86400, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /**
     * Peta durasi tiap transisi.
     *
     * Nilai: `id` (id PumpLog), `waktu` ('Y-m-d H:i:s', untuk dicocokkan dengan event_logs),
     * `dari` ('ON'/'OFF' = keadaan yang baru berakhir), `detik`, `teks` (hasil format()).
     *
     * @param  Collection<int, PumpLog>  $logs  kumpulan transisi (urutan bebas)
     * @return array<int, array{id: int, waktu: string, dari: string, detik: int, teks: string}>
     */
    public static function mapFromLogs(Collection $logs): array
    {
        $map = [];

        foreach ($logs->groupBy('device_id') as $rows) {
            // Urutkan menaik supaya "transisi sebelumnya" = keadaan yang baru berakhir.
            $rows = $rows->sortBy(fn ($row) => (string) $row->timestamp)->values();

            foreach ($rows as $i => $row) {
                // Baris paling awal di kumpulan: ambil satu transisi sebelumnya dari database
                // (per halaman paginasi, supaya baris tertua pun tetap punya durasi).
                $prev = $i > 0 ? $rows[$i - 1] : self::previousRow($row);
                if (! $prev) {
                    continue;
                }

                $seconds = (int) abs($row->timestamp->diffInSeconds($prev->timestamp));
                $map[(int) $row->id] = [
                    'id' => (int) $row->id,
                    'waktu' => $row->timestamp->format('Y-m-d H:i:s'),
                    'dari' => strtoupper((string) $prev->pump_status),
                    'detik' => $seconds,
                    'teks' => self::format($seconds),
                ];
            }
        }

        return $map;
    }

    /** Transisi tepat sebelum $row pada perangkat yang sama (nullable). */
    private static function previousRow(PumpLog $row): ?PumpLog
    {
        return PumpLog::where('device_id', $row->device_id)
            ->where('timestamp', '<', $row->timestamp)
            ->orderByDesc('timestamp')
            ->first();
    }
}
