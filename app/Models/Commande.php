<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commande extends Model
{
    protected $table = 'commandes';

    public $timestamps = false;

    protected $fillable = [
        'utilisateur_id',
        'livreur_id',
        'gestionnaire_id',
        'communes',
        'cout_global',
        'cout_livraison',
        'cout_reel',
        'statut',
        'date_reception',
        'date_livraison',
        'date_retour',
    ];

    protected function casts(): array
    {
        return [
            'date_reception' => 'date',
            'date_livraison' => 'date',
            'date_retour' => 'date',
        ];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'statut' => 'Non Livré',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }

    public function scopeForCommercial($query, int $commercialId)
    {
        return $query->whereHas('client', function ($query) use ($commercialId) {
            $query->where('commercial_id', $commercialId);
        });
    }

    public function livreur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'livreur_id');
    }
}
