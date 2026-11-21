<?php

namespace App\Models\LocationVente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GerantPaiement extends Model
{
    protected $table = 'location_vente_gerant_paiements';

    protected $fillable = [
        'gerant_id',
        'moto_id',
        'montant',
        'date_paiement',
        'mode',
        'reference',
        'notes',
        'enregistre_par',
    ];

    protected function casts(): array
    {
        return [
            'date_paiement' => 'date',
            'montant' => 'integer',
        ];
    }

    public function gerant(): BelongsTo
    {
        return $this->belongsTo(Gerant::class, 'gerant_id');
    }

    public function moto(): BelongsTo
    {
        return $this->belongsTo(Moto::class, 'moto_id');
    }
}
