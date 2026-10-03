<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_vente_contrats', function (Blueprint $table) {
            $table->unsignedBigInteger('prix_achat')->default(0)->after('moto_id');
            $table->unsignedBigInteger('cout_supplementaire')->default(0)->after('prix_achat');
            $table->unsignedBigInteger('marge')->default(0)->after('cout_supplementaire');
        });

        DB::table('location_vente_contrats')->update(['prix_achat' => DB::raw('prix_total')]);
    }

    public function down(): void
    {
        Schema::table('location_vente_contrats', function (Blueprint $table) {
            $table->dropColumn(['prix_achat', 'cout_supplementaire', 'marge']);
        });
    }
};
