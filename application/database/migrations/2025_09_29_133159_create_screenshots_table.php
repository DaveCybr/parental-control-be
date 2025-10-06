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
        Schema::create('screenshots', function (Blueprint $table) {
            $table->id();
            // PENTING: device_id adalah STRING (Android ID), bukan foreign key
            $table->string('device_id')->index();
            $table->string('file_url', 500);
            $table->timestamp('timestamp')->useCurrent();
            
            // Index untuk query cepat
            $table->index(['device_id', 'timestamp'], 'idx_device_time');
            
            // Foreign key constraint (optional)
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
        Schema::dropIfExists('screenshots');
    }
};
