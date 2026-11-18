<?php

namespace App\Models\LocationVente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Depense extends Model
{
    protected $table = 'location_vente_depenses';

    protected $fillable = [
        'moto_id',
        'contrat_id',
        'date_depense',
        'montant',
        'libelle',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_depense' => 'date',
            'montant' => 'integer',
        ];
    }

    public function moto(): BelongsTo
    {
        return $this->belongsTo(Moto::class, 'moto_id');
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class, 'contrat_id');
    }
}
