<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Alur "ubah di server → perangkat menerapkan" (Task #51):
 * - edit perangkat yang mengganti tangki/pompa/sensor harus menyinkronkan nilai turunan
 * - mengubah master data (Pengaturan → Tangki/Pompa/Sensor) harus mengirim ulang konfigurasi
 *   ke perangkat yang memakainya (nilai disinkronkan + config_update_command = 1)
 */
class ConfigPropagationTest extends TestCase
{
    use RefreshDatabase;

    private const MAC = 'C4:D8:D5:13:A6:17';

    private function adminSession(): array
    {
        return ['user' => ['id' => 1, 'username' => 'admin', 'role' => 'Administrator']];
    }

    /** @return array{device:int,tankA:int,tankB:int,pump:int,pump2:int,sensor:int} */
    private function seedMasters(): array
    {
        $tankA = (int) DB::table('tank_configurations')->insertGetId(['tank_name' => 'Tangki A', 'tank_shape' => 'kotak', 'height' => 400]);
        $tankB = (int) DB::table('tank_configurations')->insertGetId(['tank_name' => 'Tangki B', 'tank_shape' => 'kotak', 'height' => 225]);
        $pump = (int) DB::table('pumps')->insertGetId(['pump_name' => 'Pompa A', 'on_duration_seconds' => 660, 'off_duration_seconds' => 600]);
        $pump2 = (int) DB::table('pumps')->insertGetId(['pump_name' => 'Pompa B', 'on_duration_seconds' => 1800, 'off_duration_seconds' => 600]);
        $sensor = (int) DB::table('sensors')->insertGetId(['sensor_name' => 'Sensor A', 'full_tank_distance' => 25, 'trigger_percentage' => 80]);

        $device = (int) DB::table('devices')->insertGetId([
            'mac_address' => self::MAC,
            'device_type' => 'MONITOR',
            'tank_id' => $tankA,
            'pump_id' => $pump,
            'sensor_id' => $sensor,
            'status' => 'OFF',
            'control_mode' => 'AUTO',
            'full_tank_distance' => 25,
            'empty_tank_distance' => 400,
            'trigger_percentage' => 80,
            'on_duration' => 11,
            'off_duration' => 10,
            'report_interval' => 3,
        ]);

        return ['device' => $device, 'tankA' => $tankA, 'tankB' => $tankB, 'pump' => $pump, 'pump2' => $pump2, 'sensor' => $sensor];
    }

    private function device(int $id): object
    {
        return DB::table('devices')->where('id', $id)->first();
    }

    /** Edit perangkat: ganti tangki → empty_tank_distance ikut tinggi tangki baru + flag naik. */
    public function test_edit_perangkat_menyinkronkan_nilai_dari_master_baru(): void
    {
        $ids = $this->seedMasters();

        $this->withSession($this->adminSession())
            ->post('/devices/update/' . $ids['device'], [
                'device_type' => 'MONITOR',
                'tank_id' => $ids['tankB'],
                'pump_id' => $ids['pump'],
                'sensor_id' => $ids['sensor'],
                'control_mode' => 'AUTO',
                'report_interval' => 3,
                'full_tank_distance' => 25,
                'empty_tank_distance' => 400, // nilai lama yang dikirim form
                'trigger_percentage' => 80,
            ])
            ->assertRedirect(route('devices.index'));

        $device = $this->device($ids['device']);
        $this->assertSame($ids['tankB'], (int) $device->tank_id);
        $this->assertSame(225, (int) $device->empty_tank_distance, 'nilai turunan harus ikut tangki baru');
        $this->assertSame(1, (int) $device->config_update_command, 'perangkat harus diberi tahu');
        $this->assertSame(1, (int) $device->mode_update_command);

        $this->assertSame(1, (int) DB::table('admin_logs')->where('action', 'Ubah Perangkat')->count());
    }

    /** Ubah pompa di Pengaturan → durasi ON/OFF perangkat ikut (menit, dibulatkan ke atas). */
    public function test_ubah_pompa_master_dikirim_ke_perangkat(): void
    {
        $ids = $this->seedMasters();

        $this->withSession($this->adminSession())
            ->post('/settings/pumps/' . $ids['pump'], [
                'pump_name' => 'Pompa A',
                'flow_rate_lps' => 1,
                'power_hp' => 2,
                'power_watt' => 1500,
                'delay_seconds' => 30,
                'on_duration_seconds' => 1500,  // 25 menit
                'off_duration_seconds' => 601,  // 10,02 menit → 11 menit (ceil)
            ])
            ->assertRedirect();

        $device = $this->device($ids['device']);
        $this->assertSame(25, (int) $device->on_duration);
        $this->assertSame(11, (int) $device->off_duration);
        $this->assertSame(1, (int) $device->config_update_command);

        $this->assertSame(1, (int) DB::table('admin_logs')->where('action', 'Sync Perangkat')->count());
    }

    /** Ubah tangki di Pengaturan → empty_tank_distance perangkat ikut tinggi baru. */
    public function test_ubah_tangki_master_dikirim_ke_perangkat(): void
    {
        $ids = $this->seedMasters();

        $this->withSession($this->adminSession())
            ->post('/settings/tanks/' . $ids['tankA'], [
                'tank_name' => 'Tangki A',
                'tank_shape' => 'kotak',
                'height' => 300,
            ])
            ->assertRedirect();

        $device = $this->device($ids['device']);
        $this->assertSame(300, (int) $device->empty_tank_distance);
        $this->assertSame(1, (int) $device->config_update_command);
    }

    /** Ubah sensor di Pengaturan → jarak penuh & pemicu perangkat ikut berubah. */
    public function test_ubah_sensor_master_dikirim_ke_perangkat(): void
    {
        $ids = $this->seedMasters();

        $this->withSession($this->adminSession())
            ->post('/settings/sensors/' . $ids['sensor'], [
                'sensor_name' => 'Sensor A',
                'sensor_type' => 'JSN-SR04T',
                'full_tank_distance' => 30,
                'trigger_percentage' => 70,
            ])
            ->assertRedirect();

        $device = $this->device($ids['device']);
        $this->assertSame(30, (int) $device->full_tank_distance);
        $this->assertSame(70, (int) $device->trigger_percentage);
        $this->assertSame(1, (int) $device->config_update_command);
    }

    /** Master data yang tidak dipakai perangkat mana pun tidak mengubah apa-apa. */
    public function test_master_tanpa_perangkat_tidak_menulis_log(): void
    {
        $ids = $this->seedMasters();

        $this->withSession($this->adminSession())
            ->post('/settings/pumps/' . $ids['pump2'], [
                'pump_name' => 'Pompa B',
                'on_duration_seconds' => 1800,
                'off_duration_seconds' => 600,
            ])
            ->assertRedirect();

        $this->assertSame(0, (int) DB::table('admin_logs')->where('action', 'Sync Perangkat')->count());
        $this->assertSame(0, (int) $this->device($ids['device'])->config_update_command);
    }
}
