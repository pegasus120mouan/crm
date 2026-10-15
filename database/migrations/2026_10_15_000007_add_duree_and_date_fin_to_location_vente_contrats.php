<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_vente_contrats', function (Blueprint $table) {
            $table->unsignedSmallInteger('duree_mois')->nullable()->after('date_debut');
            $table->date('date_fin')->nullable()->after('duree_mois');
        });
    }

    public function down(): void
    {
        Schema::table('location_vente_contrats', function (Blueprint $table) {
            $table->dropColumn(['duree_mois', 'date_fin']);
        });
    }
};
