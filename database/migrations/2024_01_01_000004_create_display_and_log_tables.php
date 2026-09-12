<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tampilan dashboard: indikator & template gauge
        Schema::create('indicator_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('threshold_low')->default(30);
            $table->string('color_low', 7)->default('#e74c3c');
            $table->integer('threshold_medium')->default(70);
            $table->string('color_medium', 7)->default('#f39c12');
            $table->string('color_high', 7)->default('#27ae60');
            $table->string('active_template_id', 50)->default('tank_gauge');
            // Ekstensi tarif (dipakai BillingService)
            $table->float('water_price')->default(1500);
            $table->float('admin_fee')->default(5000);
        });

        Schema::create('gauge_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->longText('html_code')->nullable();
            $table->longText('css_code')->nullable();
            $table->longText('js_code')->nullable();
            $table->boolean('is_core')->default(false);
            $table->timestamps();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_type', 50);
            $table->string('title', 100);
            $table->text('message');
            $table->foreignId('device_id')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['active', 'resolved'])->default('active');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('resolved_at')->nullable();
        });

        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('action', 100);
            $table->text('details')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->text('message');
            $table->string('file', 255)->nullable();
            $table->integer('line')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->text('trace')->nullable();
            $table->text('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedBigInteger('user_id')->default(0);
            $table->text('user_agent')->nullable();
            $table->longText('payload')->nullable();
        });

        // Perangkat terdeteksi otomatis (belum terdaftar)
        Schema::create('detected_devices', function (Blueprint $table) {
            $table->id();
            $table->string('mac_address', 18)->unique();
            $table->timestamp('first_seen')->nullable()->useCurrent();
            $table->timestamp('last_seen')->nullable();
            $table->integer('hits')->default(1);
            $table->string('fingerprint', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detected_devices');
        Schema::dropIfExists('error_logs');
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('gauge_templates');
        Schema::dropIfExists('indicator_settings');
    }
};
