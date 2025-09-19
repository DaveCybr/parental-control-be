<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screen_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('parent_user_id')->constrained('users')->onDelete('cascade');
            $table->string('session_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['child_user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_sessions');
    }
};
