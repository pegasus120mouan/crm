<?php

namespace App\Models\LocationVente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Moto extends Model
{
    public const DISQUE = 'r2';

    public const CARBURANTS = [
        'essence' => 'Essence',
        'diesel' => 'Diesel',
        'electrique' => 'Électrique',
        'hybride' => 'Hybride',
    ];

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
        'carburant',
        'cylindree',
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
            'cylindree' => 'integer',
            'prix_achat' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Moto $moto) {
            $chemins = $moto->photos()->pluck('chemin')->all();
            rescue(fn () => Storage::disk(self::DISQUE)->delete($chemins), report: false);
        });
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MotoPhoto::class, 'moto_id');
    }

    /**
     * @return array<string, MotoPhoto>
     */
    public function photosParFace(): array
    {
        $photos = $this->relationLoaded('photos') ? $this->photos : $this->photos()->get();

        return $photos->keyBy('face')->all();
    }

    public function photoPrincipale(): ?MotoPhoto
    {
        $photos = $this->photosParFace();

        foreach (array_keys(MotoPhoto::FACES) as $face) {
            if (isset($photos[$face])) {
                return $photos[$face];
            }
        }

        return null;
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

    public function libelleCarburant(): ?string
    {
        return $this->carburant ? (self::CARBURANTS[$this->carburant] ?? $this->carburant) : null;
    }

    public function caracteristiques(): string
    {
        return collect([
            $this->couleur,
            $this->annee,
            $this->libelleCarburant(),
            $this->cylindree ? $this->cylindree.' cc' : null,
        ])->filter()->implode(' · ');
    }

    /**
     * Envoie toutes les photos avant d'écrire en base : si un envoi échoue,
     * les fichiers déjà envoyés sont supprimés et rien n'est enregistré.
     *
     * @param  array<string, UploadedFile>  $fichiers  photos indexées par face
     */
    public function enregistrerPhotos(array $fichiers): void
    {
        $envoyes = [];

        foreach ($fichiers as $face => $fichier) {
            $extension = strtolower($fichier->guessExtension() ?: $fichier->getClientOriginalExtension() ?: 'jpg');
            $nom = $face.'-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$extension;

            $chemin = rescue(
                fn () => Storage::disk(self::DISQUE)->putFileAs('location-vente/motos/'.$this->id, $fichier, $nom),
                false,
                report: false
            );

            if (! is_string($chemin) || $chemin === '') {
                rescue(fn () => Storage::disk(self::DISQUE)->delete(array_column($envoyes, 'chemin')), report: false);

                throw ValidationException::withMessages([
                    "photos.{$face}" => MotoPhoto::FACES[$face].' : l\'envoi vers le stockage Cloudflare R2 a échoué. Réessayez.',
                ]);
            }

            $envoyes[$face] = ['chemin' => $chemin, 'mime' => $fichier->getMimeType(), 'taille' => $fichier->getSize()];
        }

        $anciennes = $this->photosParFace();

        foreach ($envoyes as $face => $valeurs) {
            $this->photos()->updateOrCreate(['face' => $face], $valeurs);
        }

        $this->unsetRelation('photos');

        $remplacees = collect($anciennes)->only(array_keys($envoyes))->pluck('chemin')->all();
        if ($remplacees !== []) {
            rescue(fn () => Storage::disk(self::DISQUE)->delete($remplacees), report: false);
        }
    }
}
