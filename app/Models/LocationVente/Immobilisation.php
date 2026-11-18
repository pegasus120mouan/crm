<?php

namespace App\Models\LocationVente;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Immobilisation extends Model
{
    public const STATUT_EN_COURS = 'en_cours';

    public const STATUT_TERMINEE = 'terminee';

    public const STATUTS = [
        self::STATUT_EN_COURS => 'En cours',
        self::STATUT_TERMINEE => 'Terminée',
    ];

    protected $table = 'location_vente_immobilisations';

    protected $fillable = [
        'moto_id',
        'contrat_id',
        'date_debut',
        'date_fin',
        'statut',
        'motif',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
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

    public function couvre(CarbonInterface|string $date): bool
    {
        $jour = Carbon::parse($date)->startOfDay();

        if ($jour->lt($this->date_debut->copy()->startOfDay())) {
            return false;
        }

        if ($this->date_fin === null) {
            return true;
        }

        return $jour->lte($this->date_fin->copy()->startOfDay());
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function periodeLibelle(): string
    {
        $debut = $this->date_debut->format('d/m/Y');

        if ($this->date_fin === null) {
            return 'depuis le '.$debut;
        }

        return 'du '.$debut.' au '.$this->date_fin->format('d/m/Y');
    }
}
