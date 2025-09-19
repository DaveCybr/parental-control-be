<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_info', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('device_id')->unique();
            $table->string('device_type', 50); // android, ios
            $table->string('device_name')->nullable();
            $table->string('app_version', 20);
            $table->string('os_version', 50)->nullable();
            $table->timestamp('last_activity');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('last_activity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_info');
    }
};
