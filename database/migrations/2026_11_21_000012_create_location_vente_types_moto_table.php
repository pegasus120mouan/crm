<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_vente_types_moto', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->timestamps();
        });

        Schema::table('location_vente_motos', function (Blueprint $table) {
            $table->foreignId('type_moto_id')
                ->nullable()
                ->after('modele')
                ->constrained('location_vente_types_moto')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('location_vente_motos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('type_moto_id');
        });

        Schema::dropIfExists('location_vente_types_moto');
    }
};
