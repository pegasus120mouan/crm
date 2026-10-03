<?php

namespace App\Models\LocationVente;

use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    public const TYPE_APPORT = 'apport';

    public const TYPE_ECHEANCE = 'echeance';

    public const MODES = [
        'Espèces',
        'Wave',
        'Orange Money',
        'MTN Mobile Money',
        'Moov Money',
        'Virement bancaire',
    ];

    protected $table = 'location_vente_paiements';

    protected $fillable = [
        'contrat_id',
        'montant',
        'date_paiement',
        'type',
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

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class, 'contrat_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'enregistre_par');
    }
}
