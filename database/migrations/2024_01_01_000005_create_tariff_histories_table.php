<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Histori perubahan tarif air & biaya admin (kapan / bulan berubah, oleh siapa, dari nilai apa)
        Schema::create('tariff_histories', function (Blueprint $table) {
            $table->id();
            $table->float('water_price');          // nilai baru (Rp / m³)
            $table->float('admin_fee');            // nilai baru (biaya admin bulanan)
            $table->float('old_water_price')->nullable();
            $table->float('old_admin_fee')->nullable();
            $table->string('changed_by', 50)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_histories');
    }
};
