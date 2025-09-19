<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('family_code', 8)->unique();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};

// // Migration: create_family_members_table.php

// // Migration: create_locations_table.php


// // Migration: create_notification_mirrors_table.php

// // Migration: create_geofences_table.php

// // Migration: create_screen_sessions_table.php

// // Migration: create_alerts_table.php
// <?php
// use Illuminate\Database\Migrations\Migration;
// use Illuminate\Database\Schema\Blueprint;
// use Illuminate\Support\Facades\Schema;

// return new class extends Migration
// {
//     public function up(): void
//     {
//         Schema::create('alerts', function (Blueprint $table) {
//             $table->id();
//             $table->foreignId('child_user_id')->constrained('users')->onDelete('cascade');
//             $table->enum('type', ['geofence', 'emergency', 'content', 'battery']);
//             $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');
//             $table->string('title');
//             $table->text('message');
//             $table->json('data')->nullable(); // Additional data for alert
//             $table->boolean('is_read')->default(false);
//             $table->timestamp('triggered_at');
//             $table->timestamps();

//             $table->index(['child_user_id', 'is_read']);
//             $table->index(['child_user_id', 'priority']);
//         });
//     }

//     public function down(): void
//     {
//         Schema::dropIfExists('alerts');
//     }
// };

// // Migration: create_app_settings_table.php
// <?php
// use Illuminate\Database\Migrations\Migration;
// use Illuminate\Database\Schema\Blueprint;
// use Illuminate\Support\Facades\Schema;

// return new class extends Migration
// {
//     public function up(): void
//     {
//         Schema::create('app_settings', function (Blueprint $table) {
//             $table->id();
//             $table->foreignId('family_id')->constrained()->onDelete('cascade');
//             $table->foreignId('child_user_id')->constrained('users')->onDelete('cascade');
//             $table->json('notification_filters'); // Which apps to monitor
//             $table->json('blocked_keywords'); // Blocked words in notifications
//             $table->integer('location_update_interval')->default(60); // seconds
//             $table->boolean('screen_mirroring_enabled')->default(false);
//             $table->json('geofence_settings'); // Geofence configuration
//             $table->timestamps();

//             $table->unique(['family_id', 'child_user_id']);
//         });
//     }

//     public function down(): void
//     {
//         Schema::dropIfExists('app_settings');
//     }
// };