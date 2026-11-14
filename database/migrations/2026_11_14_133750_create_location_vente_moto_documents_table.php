<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_vente_moto_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moto_id')->constrained('location_vente_motos')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('numero', 50)->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->string('chemin')->nullable();
            $table->string('nom_original')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('taille')->nullable();
            $table->timestamps();
            $table->unique(['moto_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_vente_moto_documents');
    }
};
