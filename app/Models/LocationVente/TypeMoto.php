<?php

namespace App\Models\LocationVente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeMoto extends Model
{
    protected $table = 'location_vente_types_moto';

    protected $fillable = [
        'nom',
        'cout_acquisition',
        'marge',
        'montant_journalier',
    ];

    protected function casts(): array
    {
        return [
            'cout_acquisition' => 'integer',
            'marge' => 'integer',
            'montant_journalier' => 'integer',
        ];
    }

    public function motos(): HasMany
    {
        return $this->hasMany(Moto::class, 'type_moto_id');
    }
}
