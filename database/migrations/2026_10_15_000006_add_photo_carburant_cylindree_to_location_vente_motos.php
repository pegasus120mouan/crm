<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_vente_motos', function (Blueprint $table) {
            $table->string('carburant', 20)->nullable()->after('annee');
            $table->unsignedSmallInteger('cylindree')->nullable()->after('carburant');
        });

        Schema::create('location_vente_moto_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moto_id')->constrained('location_vente_motos')->cascadeOnDelete();
            $table->string('face', 20);
            $table->string('chemin');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('taille')->nullable();
            $table->timestamps();
            $table->unique(['moto_id', 'face']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_vente_moto_photos');

        Schema::table('location_vente_motos', function (Blueprint $table) {
            $table->dropColumn(['carburant', 'cylindree']);
        });
    }
};
