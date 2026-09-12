<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id');
            $table->float('water_level')->default(0);
            $table->float('water_percentage')->default(0);
            $table->integer('rssi')->default(0);
            $table->timestamp('record_time')->useCurrent();
            $table->index(['device_id', 'record_time']);
        });
        Schema::create('pump_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id');
            $table->enum('pump_status', ['ON', 'OFF']);
            $table->enum('control_mode', ['AUTO', 'MANUAL', 'TIMED'])->default('AUTO');
            $table->integer('duration_seconds')->default(0);
            $table->timestamp('timestamp')->useCurrent();
            $table->index(['device_id', 'timestamp']);
        });
        Schema::create('event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id');
            $table->string('event_type', 50);
            $table->text('message')->nullable();
            $table->timestamp('event_time')->useCurrent();
        });
        Schema::create('minute_sensor_logs', function (Blueprint $table) {
            $table->foreignId('device_id');
            $table->datetime('minute_timestamp');
            $table->float('avg_water_level');
            $table->primary(['device_id', 'minute_timestamp']);
        });
        Schema::create('hourly_sensor_logs', function (Blueprint $table) {
            $table->foreignId('device_id');
            $table->datetime('hour_timestamp');
            $table->float('avg_water_level');
            $table->primary(['device_id', 'hour_timestamp']);
        });
        Schema::create('daily_sensor_logs', function (Blueprint $table) {
            $table->foreignId('device_id');
            $table->date('day_timestamp');
            $table->float('avg_water_level');
            $table->primary(['device_id', 'day_timestamp']);
        });
        Schema::create('weekly_sensor_logs', function (Blueprint $table) {
            $table->foreignId('device_id');
            $table->date('week_timestamp');
            $table->float('avg_water_level');
            $table->primary(['device_id', 'week_timestamp']);
        });
        Schema::create('half_hourly_sensor_logs', function (Blueprint $table) {
            $table->foreignId('device_id');
            $table->datetime('half_hour_timestamp');
            $table->float('avg_water_level');
            $table->primary(['device_id', 'half_hour_timestamp']);
        });
        Schema::create('quarter_hourly_sensor_logs', function (Blueprint $table) {
            $table->foreignId('device_id');
            $table->datetime('quarter_hour_timestamp');
            $table->float('avg_water_level');
            $table->primary(['device_id', 'quarter_hour_timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarter_hourly_sensor_logs');
        Schema::dropIfExists('half_hourly_sensor_logs');
        Schema::dropIfExists('weekly_sensor_logs');
        Schema::dropIfExists('daily_sensor_logs');
        Schema::dropIfExists('hourly_sensor_logs');
        Schema::dropIfExists('minute_sensor_logs');
        Schema::dropIfExists('event_logs');
        Schema::dropIfExists('pump_logs');
        Schema::dropIfExists('sensor_logs');
    }
};
