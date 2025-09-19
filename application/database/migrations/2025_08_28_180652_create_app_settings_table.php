<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->onDelete('cascade');
            $table->foreignId('child_user_id')->constrained('users')->onDelete('cascade');
            $table->json('notification_filters'); // Which apps to monitor
            $table->json('blocked_keywords'); // Blocked words in notifications
            $table->integer('location_update_interval')->default(60); // seconds
            $table->boolean('screen_mirroring_enabled')->default(false);
            $table->json('geofence_settings'); // Geofence configuration
            $table->timestamps();

            $table->unique(['family_id', 'child_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
