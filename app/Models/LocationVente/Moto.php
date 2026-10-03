<?php

namespace App\Models\LocationVente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Moto extends Model
{
    public const STATUT_DISPONIBLE = 'disponible';

    public const STATUT_EN_LOCATION = 'en_location';

    public const STATUT_MAINTENANCE = 'maintenance';

    public const STATUT_CEDEE = 'cedee';

    public const STATUTS = [
        self::STATUT_DISPONIBLE => 'Disponible',
        self::STATUT_EN_LOCATION => 'En location-vente',
        self::STATUT_MAINTENANCE => 'En maintenance',
        self::STATUT_CEDEE => 'Cédée (hors parc)',
    ];

    /**
     * Statuts modifiables à la main ; les autres sont gérés par les contrats.
     */
    public const STATUTS_MANUELS = [self::STATUT_DISPONIBLE, self::STATUT_MAINTENANCE];

    protected $table = 'location_vente_motos';

    protected $fillable = [
        'marque',
        'modele',
        'immatriculation',
        'numero_chassis',
        'couleur',
        'annee',
        'prix_achat',
        'date_acquisition',
        'statut',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_acquisition' => 'date',
            'annee' => 'integer',
            'prix_achat' => 'integer',
        ];
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class, 'moto_id');
    }

    public function contratActuel(): HasOne
    {
        return $this->hasOne(Contrat::class, 'moto_id')
            ->whereIn('statut', [Contrat::STATUT_EN_COURS, Contrat::STATUT_SOLDE])
            ->latestOfMany();
    }

    public function scopeDansLeParc($query)
    {
        return $query->where('statut', '!=', self::STATUT_CEDEE);
    }

    public function scopeDisponibles($query)
    {
        return $query->where('statut', self::STATUT_DISPONIBLE)->orderBy('marque')->orderBy('immatriculation');
    }

    public function libelle(): string
    {
        return trim($this->marque.' '.$this->modele).' · '.$this->immatriculationAffichee();
    }

    public function immatriculationEnCours(): bool
    {
        return blank($this->immatriculation);
    }

    public function immatriculationAffichee(): string
    {
        if (! $this->immatriculationEnCours()) {
            return $this->immatriculation;
        }

        return $this->numero_chassis
            ? "Immat. en cours (châssis {$this->numero_chassis})"
            : 'Immat. en cours';
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function couleurStatut(): string
    {
        return match ($this->statut) {
            self::STATUT_DISPONIBLE => 'success',
            self::STATUT_EN_LOCATION => 'primary',
            self::STATUT_MAINTENANCE => 'warning',
            default => 'secondary',
        };
    }
}
