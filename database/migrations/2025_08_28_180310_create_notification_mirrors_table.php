<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_mirrors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_user_id')->constrained('users')->onDelete('cascade');
            $table->string('app_package');
            $table->string('title');
            $table->text('content');
            $table->integer('priority')->default(3); // 1=lowest, 5=highest
            $table->string('category')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_flagged')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();

            $table->index(['child_user_id', 'timestamp']);
            $table->index(['child_user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_mirrors');
    }
};
