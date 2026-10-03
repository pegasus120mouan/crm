<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_vente_livreurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenoms');
            $table->string('contact', 30);
            $table->string('contact_urgence', 30)->nullable();
            $table->string('numero_piece', 50)->nullable();
            $table->string('numero_permis', 50)->nullable();
            $table->string('adresse')->nullable();
            $table->boolean('statut')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('location_vente_motos', function (Blueprint $table) {
            $table->id();
            $table->string('marque', 100);
            $table->string('modele', 100)->nullable();
            $table->string('immatriculation', 50)->unique();
            $table->string('numero_chassis', 100)->nullable()->unique();
            $table->string('couleur', 50)->nullable();
            $table->unsignedSmallInteger('annee')->nullable();
            $table->unsignedBigInteger('prix_achat')->nullable();
            $table->date('date_acquisition')->nullable();
            $table->string('statut', 20)->default('disponible')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('location_vente_contrats', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->nullable()->unique();
            $table->foreignId('livreur_id')->constrained('location_vente_livreurs')->restrictOnDelete();
            $table->foreignId('moto_id')->constrained('location_vente_motos')->restrictOnDelete();
            $table->unsignedBigInteger('prix_total');
            $table->unsignedBigInteger('apport')->default(0);
            $table->unsignedBigInteger('montant_echeance');
            $table->string('frequence', 20);
            $table->date('date_debut');
            $table->string('statut', 20)->default('en_cours')->index();
            $table->date('date_solde')->nullable();
            $table->date('date_resiliation')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('location_vente_paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrat_id')->constrained('location_vente_contrats')->cascadeOnDelete();
            $table->unsignedBigInteger('montant');
            $table->date('date_paiement')->index();
            $table->string('type', 20)->default('echeance');
            $table->string('mode', 50);
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('enregistre_par')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_vente_paiements');
        Schema::dropIfExists('location_vente_contrats');
        Schema::dropIfExists('location_vente_motos');
        Schema::dropIfExists('location_vente_livreurs');
    }
};
