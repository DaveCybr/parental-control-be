<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            // PENTING: device_id adalah STRING (Android ID), bukan foreign key
            $table->string('device_id')->index();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('timestamp')->useCurrent();
            
            // Index untuk query cepat
            $table->index(['device_id', 'timestamp'], 'idx_device_time');
            
            // Foreign key constraint (optional, untuk data integrity)
            // Relasi ke devices.device_id (bukan devices.id)
            $table->foreign('device_id')
                  ->references('device_id')
                  ->on('devices')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
