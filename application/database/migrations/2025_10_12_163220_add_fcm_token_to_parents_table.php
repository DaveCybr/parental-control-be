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
        Schema::table('parents', function (Blueprint $table) {
            $table->string('fcm_token', 255)->nullable()->after('family_code');
            $table->timestamp('fcm_token_updated_at')->nullable()->after('fcm_token');

            // Index untuk query cepat
            $table->index('fcm_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            $table->dropColumn(['fcm_token', 'fcm_token_updated_at']);
        });
    }
};
