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
        Schema::create('captured_photos', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->index();
            $table->enum('camera_type', ['front', 'back']);
            $table->string('file_url', 500);
            $table->timestamp('captured_at')->useCurrent();

            // Index untuk query cepat
            $table->index(['device_id', 'captured_at'], 'idx_device_time');

            // Foreign key constraint
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
        Schema::dropIfExists('captured_photos');
    }
};
