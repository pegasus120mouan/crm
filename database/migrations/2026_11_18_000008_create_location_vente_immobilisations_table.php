<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_vente_immobilisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moto_id')->constrained('location_vente_motos')->cascadeOnDelete();
            $table->foreignId('contrat_id')->nullable()->constrained('location_vente_contrats')->nullOnDelete();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('statut', 20);
            $table->string('motif', 500)->nullable();
            $table->timestamps();

            $table->index(['moto_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_vente_immobilisations');
    }
};
