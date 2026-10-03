<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_vente_livreurs', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
            $table->string('photo')->nullable()->after('code');
            $table->date('date_naissance')->nullable()->after('prenoms');
            $table->string('type_piece', 30)->nullable()->after('adresse');
            $table->date('date_expiration_piece')->nullable()->after('numero_piece');
            $table->string('categorie_permis', 20)->nullable()->after('numero_permis');
            $table->date('date_expiration_permis')->nullable()->after('categorie_permis');
            $table->string('kyc_statut', 20)->default('incomplet')->index()->after('statut');
            $table->timestamp('kyc_verifie_le')->nullable()->after('kyc_statut');
            $table->unsignedBigInteger('kyc_verifie_par')->nullable()->after('kyc_verifie_le');
            $table->string('kyc_motif_rejet')->nullable()->after('kyc_verifie_par');
        });

        Schema::create('location_vente_livreur_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livreur_id')->constrained('location_vente_livreurs')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('chemin');
            $table->string('nom_original')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('taille')->nullable();
            $table->timestamps();
            $table->unique(['livreur_id', 'type']);
        });

        DB::table('location_vente_livreurs')->whereNull('code')->orderBy('id')->pluck('id')->each(function ($id) {
            DB::table('location_vente_livreurs')->where('id', $id)->update(['code' => 'LVR-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT)]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_vente_livreur_documents');

        Schema::table('location_vente_livreurs', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropIndex(['kyc_statut']);
            $table->dropColumn([
                'code', 'photo', 'date_naissance', 'type_piece', 'date_expiration_piece',
                'categorie_permis', 'date_expiration_permis', 'kyc_statut', 'kyc_verifie_le',
                'kyc_verifie_par', 'kyc_motif_rejet',
            ]);
        });
    }
};
