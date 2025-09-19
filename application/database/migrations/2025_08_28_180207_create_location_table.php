<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy', 8, 2);
            $table->integer('battery_level');
            $table->timestamp('timestamp');
            $table->timestamps();

            $table->index(['user_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
