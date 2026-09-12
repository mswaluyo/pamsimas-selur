<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pelanggan
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id', 50)->unique();
            $table->string('name', 150);
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('lid', 50)->nullable()->index();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        // Antrean validasi foto meteran (WA OCR)
        Schema::create('customer_validations', function (Blueprint $table) {
            $table->string('session_id', 64)->primary();
            $table->string('lid', 50)->nullable()->index();
            $table->string('phone', 50);
            $table->integer('angka_sementara')->default(0);
            $table->string('foto_path', 255);
            $table->enum('status', ['MENUNGGU', 'KONFIRMASI', 'DIVALIDASI_WARGA', 'SELESAI'])->default('MENUNGGU');
            $table->timestamps();
            $table->index(['phone', 'status']);
        });

        // Pencatatan meter
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id', 50);
            $table->string('period', 7);
            $table->decimal('current_meter', 15, 2);
            $table->string('photo_path', 255)->nullable();
            $table->unsignedBigInteger('validated_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->unique(['customer_id', 'period']);
        });

        // Tagihan
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meter_reading_id')->nullable();
            $table->string('customer_id', 50);
            $table->string('period', 7);
            $table->float('water_usage')->default(0);
            $table->float('water_price')->default(0);
            $table->float('admin_fee')->default(0);
            $table->float('total_bill')->default(0);
            $table->enum('status_bayar', ['BELUM', 'LUNAS'])->default('BELUM');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->unique(['customer_id', 'period']);
        });

        // Pembayaran
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id');
            $table->string('customer_id', 50);
            $table->string('period', 7);
            $table->float('amount')->default(0);
            $table->enum('method', ['TUNAI', 'TRANSFER'])->default('TUNAI');
            $table->string('receipt_number', 50)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('paid_at')->nullable()->useCurrent();
        });

        // Pengaturan keuangan (tarif)
        Schema::create('financial_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key_name', 50)->unique();
            $table->string('value', 100);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_settings');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('meter_readings');
        Schema::dropIfExists('customer_validations');
        Schema::dropIfExists('customers');
    }
};
