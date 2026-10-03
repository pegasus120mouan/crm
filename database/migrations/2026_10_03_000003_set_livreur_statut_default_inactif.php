<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_vente_livreurs', function (Blueprint $table) {
            $table->boolean('statut')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('location_vente_livreurs', function (Blueprint $table) {
            $table->boolean('statut')->default(true)->change();
        });
    }
};
