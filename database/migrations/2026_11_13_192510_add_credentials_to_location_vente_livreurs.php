<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_vente_livreurs', function (Blueprint $table) {
            $table->string('login')->nullable()->unique()->after('code');
            $table->string('code_otp')->nullable()->after('login');
        });
    }

    public function down(): void
    {
        Schema::table('location_vente_livreurs', function (Blueprint $table) {
            $table->dropUnique(['login']);
            $table->dropColumn(['login', 'code_otp']);
        });
    }
};
