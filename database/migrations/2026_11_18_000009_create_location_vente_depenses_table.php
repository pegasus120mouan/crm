<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_vente_depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moto_id')->constrained('location_vente_motos')->cascadeOnDelete();
            $table->foreignId('contrat_id')->nullable()->constrained('location_vente_contrats')->nullOnDelete();
            $table->date('date_depense');
            $table->unsignedInteger('montant');
            $table->string('libelle');
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_vente_depenses');
    }
};
