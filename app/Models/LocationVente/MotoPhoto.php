<?php

namespace App\Models\LocationVente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MotoPhoto extends Model
{
    /**
     * Ordre d'affichage ; la première face disponible sert de photo principale.
     */
    public const FACES = [
        'droite' => 'Côté droit',
        'gauche' => 'Côté gauche',
        'avant' => 'Face avant',
        'arriere' => 'Face arrière',
    ];

    protected $table = 'location_vente_moto_photos';

    protected $fillable = [
        'moto_id',
        'face',
        'chemin',
        'mime',
        'taille',
    ];

    public function moto(): BelongsTo
    {
        return $this->belongsTo(Moto::class, 'moto_id');
    }

    public function libelle(): string
    {
        return self::FACES[$this->face] ?? $this->face;
    }
}
