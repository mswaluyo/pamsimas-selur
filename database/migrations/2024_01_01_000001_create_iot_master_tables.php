<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tangki + dimensi
        Schema::create('tank_rectangular_dimensions', function (Blueprint $table) {
            $table->id();
            $table->float('length')->default(0);
            $table->float('width')->default(0);
        });
        Schema::create('tank_circular_dimensions', function (Blueprint $table) {
            $table->id();
            $table->float('diameter')->default(0);
        });
        Schema::create('tank_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('tank_name', 100);
            $table->enum('tank_shape', ['kotak', 'bulat'])->default('kotak');
            $table->float('height')->default(0);
            $table->foreignId('rectangular_dim_id')->nullable();
            $table->foreignId('circular_dim_id')->nullable();
        });

        // Pompa & Sensor (master data)
        Schema::create('pumps', function (Blueprint $table) {
            $table->id();
            $table->string('pump_name', 100);
            $table->float('flow_rate_lps')->default(0);
            $table->float('power_hp')->nullable();
            $table->integer('power_watt')->default(0);
            $table->integer('delay_seconds')->default(0);
            $table->integer('on_duration_seconds')->default(300);
            $table->integer('off_duration_seconds')->default(900);
        });
        Schema::create('sensors', function (Blueprint $table) {
            $table->id();
            $table->string('sensor_name', 100);
            $table->string('sensor_type', 50)->default('JSN-SR04T');
            $table->integer('full_tank_distance')->default(30);
            $table->integer('trigger_percentage')->default(70);
            $table->timestamps();
        });

        // Perangkat IoT
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('mac_address', 18)->unique();
            $table->enum('device_type', ['MONITOR', 'ACTUATOR'])->default('MONITOR');
            $table->foreignId('tank_id');
            $table->foreignId('pump_id');
            $table->foreignId('sensor_id')->nullable();
            $table->enum('status', ['ON', 'OFF'])->default('OFF');
            $table->enum('control_mode', ['AUTO', 'MANUAL', 'TIMED'])->default('AUTO');
            $table->timestamp('last_update')->nullable();
            $table->integer('rssi')->default(0);
            $table->integer('full_tank_distance')->default(30);
            $table->integer('empty_tank_distance')->default(200);
            $table->integer('trigger_percentage')->default(70);
            $table->integer('min_run_time')->default(60);
            $table->integer('sensor_debounce')->default(5);
            $table->integer('report_interval')->default(3);
            $table->integer('on_duration')->default(15);
            $table->integer('off_duration')->default(30);
            $table->boolean('restart_command')->default(false);
            $table->boolean('config_update_command')->default(false);
            $table->boolean('mode_update_command')->default(false);
            $table->unsignedBigInteger('uptime')->default(0);
            $table->integer('free_heap')->default(0);
            $table->string('reset_reason', 255)->default('');
            $table->string('firmware_version', 20)->default('1.0.0');
            $table->string('firmware_build_date', 50)->default('');
            $table->timestamp('last_offline_sync')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
        Schema::dropIfExists('sensors');
        Schema::dropIfExists('pumps');
        Schema::dropIfExists('tank_configurations');
        Schema::dropIfExists('tank_circular_dimensions');
        Schema::dropIfExists('tank_rectangular_dimensions');
    }
};
