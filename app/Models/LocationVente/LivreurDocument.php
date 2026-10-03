<?php

namespace App\Models\LocationVente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LivreurDocument extends Model
{
    public const TYPES = [
        'piece_recto' => "Pièce d'identité – recto",
        'piece_verso' => "Pièce d'identité – verso",
        'permis_recto' => 'Permis de conduire – recto',
        'permis_verso' => 'Permis de conduire – verso',
        'justificatif_domicile' => 'Justificatif de domicile',
    ];

    public const OBLIGATOIRES = ['piece_recto', 'piece_verso', 'permis_recto', 'permis_verso'];

    protected $table = 'location_vente_livreur_documents';

    protected $fillable = [
        'livreur_id',
        'type',
        'chemin',
        'nom_original',
        'mime',
        'taille',
    ];

    public function livreur(): BelongsTo
    {
        return $this->belongsTo(Livreur::class, 'livreur_id');
    }

    public function libelle(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function estImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
