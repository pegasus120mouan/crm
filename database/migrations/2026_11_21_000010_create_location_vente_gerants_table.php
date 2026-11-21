<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_vente_gerants', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('nom');
            $table->string('prenoms')->nullable();
            $table->string('contact')->nullable();
            $table->string('adresse')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('statut')->default(true);
            $table->timestamps();
        });

        Schema::table('location_vente_motos', function (Blueprint $table) {
            $table->foreignId('gerant_id')
                ->nullable()
                ->after('statut')
                ->constrained('location_vente_gerants')
                ->nullOnDelete();
        });

        Schema::create('location_vente_gerant_paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gerant_id')->constrained('location_vente_gerants')->cascadeOnDelete();
            $table->foreignId('moto_id')->constrained('location_vente_motos')->cascadeOnDelete();
            $table->unsignedInteger('montant');
            $table->date('date_paiement');
            $table->string('mode', 50);
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('enregistre_par')->nullable();
            $table->timestamps();

            $table->index(['moto_id', 'date_paiement']);
            $table->index(['gerant_id', 'date_paiement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_vente_gerant_paiements');

        Schema::table('location_vente_motos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gerant_id');
        });

        Schema::dropIfExists('location_vente_gerants');
    }
};
