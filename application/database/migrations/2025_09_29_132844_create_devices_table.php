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
        Schema::create('devices', function (Blueprint $table) {
            $table->id(); // Auto-increment ID
            $table->foreignId('parent_id')->constrained('parents')->onDelete('cascade');
            $table->string('device_id')->unique(); // Android ID (string)
            $table->string('device_name');
            $table->enum('device_type', ['android', 'ios']);
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_seen')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
            
            // Index untuk performa
            $table->index('device_id');
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
