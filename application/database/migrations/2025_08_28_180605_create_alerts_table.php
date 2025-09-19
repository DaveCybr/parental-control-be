<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['geofence', 'emergency', 'content', 'battery']);
            $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data for alert
            $table->boolean('is_read')->default(false);
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->index(['child_user_id', 'is_read']);
            $table->index(['child_user_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
