<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kontrak POST /api/update untuk ack flag firmware (audit Task #50):
 * firmware mengirim reset_config / reset_mode_update / reset_ota_update / reset_restart
 * dan sebelumnya semuanya dibalas HTTP 400 "Unknown action".
 */
class DeviceAckActionTest extends TestCase
{
    use RefreshDatabase;

    private const MAC = 'C4:D8:D5:13:A6:17';

    /** Buat data master minimal + perangkat dengan semua flag one-shot aktif. */
    private function seedDevice(): int
    {
        $tankId = DB::table('tank_configurations')->insertGetId([
            'tank_name' => 'Tank Uji',
            'tank_shape' => 'kotak',
            'height' => 200,
        ]);
        $pumpId = DB::table('pumps')->insertGetId(['pump_name' => 'Pompa Uji']);

        return (int) DB::table('devices')->insertGetId([
            'mac_address' => self::MAC,
            'device_type' => 'MONITOR',
            'tank_id' => $tankId,
            'pump_id' => $pumpId,
            'status' => 'OFF',
            'control_mode' => 'AUTO',
            'config_update_command' => 1,
            'mode_update_command' => 1,
            'restart_command' => 1,
        ]);
    }

    private function flag(int $deviceId, string $column): int
    {
        return (int) DB::table('devices')->where('id', $deviceId)->value($column);
    }

    public function test_ack_firmware_dibalas_200_dan_flag_dibersihkan(): void
    {
        $id = $this->seedDevice();

        $this->postJson('/api/update', ['mac' => self::MAC, 'action' => 'reset_config', 'value' => '0'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('config_update_command', 0);
        $this->assertSame(0, $this->flag($id, 'config_update_command'));

        $this->postJson('/api/update', ['mac' => self::MAC, 'action' => 'reset_mode_update', 'value' => '0'])
            ->assertStatus(200)
            ->assertJsonPath('mode_update_command', 0);
        $this->assertSame(0, $this->flag($id, 'mode_update_command'));

        $this->postJson('/api/update', ['mac' => self::MAC, 'action' => 'reset_restart', 'value' => '0'])
            ->assertStatus(200)
            ->assertJsonPath('restart_command', 0);
        $this->assertSame(0, $this->flag($id, 'restart_command'));

        // Tidak ada kolom ota_update_command pada skema baru → tetap 200, bukan 400.
        $this->postJson('/api/update', ['mac' => self::MAC, 'action' => 'reset_ota_update', 'value' => '0'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    public function test_action_tak_dikenal_tetap_400(): void
    {
        $this->seedDevice();

        $this->postJson('/api/update', ['mac' => self::MAC, 'action' => 'aksi_ngawur', 'value' => '0'])
            ->assertStatus(400)
            ->assertJsonPath('status', 'error');
    }

    public function test_mac_tidak_dikenal_dibalas_unregistered(): void
    {
        $this->seedDevice();

        $this->postJson('/api/update', ['mac' => '11:22:33:44:55:66', 'action' => 'reset_config', 'value' => '0'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'unregistered');
    }

    /** Status mengirim konfigurasi dalam DETIK (DB menyimpan menit) lalu mereset flag. */
    public function test_status_mengirim_konfigurasi_dan_mereset_flag(): void
    {
        $id = $this->seedDevice();
        DB::table('devices')->where('id', $id)->update(['on_duration' => 11, 'off_duration' => 10]);

        $response = $this->getJson('/api/status?mac=' . self::MAC);
        $response->assertStatus(200)
            ->assertJsonPath('on_duration', 660)   // 11 menit x 60
            ->assertJsonPath('off_duration', 600)  // 10 menit x 60
            ->assertJsonPath('config_update_command', 1)
            ->assertJsonPath('mode_update_command', 1);

        $this->assertSame(0, $this->flag($id, 'config_update_command'));
        $this->assertSame(0, $this->flag($id, 'mode_update_command'));
    }
}
