<?php

namespace App\Models\LocationVente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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

    public const STATUT_CHEZ_GERANT = 'chez_gerant';

    public const STATUT_MAINTENANCE = 'maintenance';

    public const STATUT_EN_PANNE = 'en_panne';

    public const STATUT_CEDEE = 'cedee';

    public const STATUTS = [
        self::STATUT_DISPONIBLE => 'Disponible',
        self::STATUT_EN_LOCATION => 'En location-vente',
        self::STATUT_CHEZ_GERANT => 'Confiée à un gérant',
        self::STATUT_EN_PANNE => 'En panne',
        self::STATUT_MAINTENANCE => 'En maintenance',
        self::STATUT_CEDEE => 'Cédée (hors parc)',
    ];

    /**
     * Statuts modifiables à la main ; les autres sont gérés par les contrats / confiage.
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
        'gerant_id',
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
            $chemins = $moto->photos()->pluck('chemin')
                ->merge($moto->documents()->pluck('chemin'))
                ->filter()
                ->all();
            rescue(fn () => Storage::disk(self::DISQUE)->delete($chemins), report: false);
        });
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MotoPhoto::class, 'moto_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MotoDocument::class, 'moto_id');
    }

    /**
     * @return array<string, MotoDocument>
     */
    public function documentsParType(): array
    {
        $documents = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();

        return $documents->keyBy('type')->all();
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

    public function contratEnCours(): HasOne
    {
        return $this->hasOne(Contrat::class, 'moto_id')->ofMany(
            ['date_debut' => 'max', 'id' => 'max'],
            fn ($query) => $query->where('statut', Contrat::STATUT_EN_COURS)
        );
    }

    public function immobilisations(): HasMany
    {
        return $this->hasMany(Immobilisation::class, 'moto_id')->orderByDesc('date_debut');
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class, 'moto_id');
    }

    public function paiements(): HasManyThrough
    {
        return $this->hasManyThrough(Paiement::class, Contrat::class, 'moto_id', 'contrat_id');
    }

    /**
     * Résultat de la moto : montants encaissés − dépenses effectuées.
     *
     * @return array{prix_achat: int, total_depenses: int, total_paiements: int, resultat: int, ratio: int|null, sens: string}
     */
    public function bilanFinancier(): array
    {
        $totalDepenses = array_key_exists('depenses_sum_montant', $this->attributes)
            ? (int) $this->attributes['depenses_sum_montant']
            : ($this->relationLoaded('depenses')
                ? (int) $this->depenses->sum('montant')
                : (int) $this->depenses()->sum('montant'));
        $totalPaiements = array_key_exists('paiements_sum_montant', $this->attributes)
            ? (int) $this->attributes['paiements_sum_montant']
            : ($this->relationLoaded('paiements')
                ? (int) $this->paiements->sum('montant')
                : (int) $this->paiements()->sum('montant'));
        $resultat = $totalPaiements - $totalDepenses;

        return [
            'prix_achat' => (int) $this->prix_achat,
            'total_depenses' => $totalDepenses,
            'total_paiements' => $totalPaiements,
            'resultat' => $resultat,
            'ratio' => $totalDepenses > 0 ? (int) round($totalPaiements * 100 / $totalDepenses) : null,
            'sens' => match (true) {
                $resultat > 0 => 'gain',
                $resultat < 0 => 'perte',
                default => 'equilibre',
            },
        ];
    }

    public function immobilisationEnCours(): HasOne
    {
        return $this->hasOne(Immobilisation::class, 'moto_id')->ofMany(
            ['date_debut' => 'max', 'id' => 'max'],
            fn ($query) => $query->where('statut', Immobilisation::STATUT_EN_COURS)
        );
    }

    public function gerant(): BelongsTo
    {
        return $this->belongsTo(Gerant::class, 'gerant_id');
    }

    public function paiementsGerant(): HasMany
    {
        return $this->hasMany(GerantPaiement::class, 'moto_id');
    }

    public function peutEtreConfieeAUnGerant(): bool
    {
        return $this->statut === self::STATUT_DISPONIBLE
            && ! $this->contrats()->where('statut', Contrat::STATUT_EN_COURS)->exists();
    }

    public function estChezGerant(): bool
    {
        return $this->statut === self::STATUT_CHEZ_GERANT && $this->gerant_id !== null;
    }

    public function peutEtreDeclareeEnPanne(): bool
    {
        return ! in_array($this->statut, [self::STATUT_CEDEE, self::STATUT_EN_PANNE], true);
    }

    public function statutApresPanne(): string
    {
        if ($this->contrats()->where('statut', Contrat::STATUT_EN_COURS)->exists()) {
            return self::STATUT_EN_LOCATION;
        }

        return $this->gerant_id ? self::STATUT_CHEZ_GERANT : self::STATUT_DISPONIBLE;
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

    public function numeroPlaque(): ?string
    {
        if (! $this->immatriculationEnCours()) {
            return $this->immatriculation;
        }

        $carteGrise = $this->documentsParType()[MotoDocument::TYPE_CARTE_GRISE] ?? null;

        return $carteGrise?->numero;
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

    public function libelleStatutDocuments(): string
    {
        return match ($this->statut) {
            self::STATUT_EN_LOCATION => 'En Utilisation',
            self::STATUT_CHEZ_GERANT => 'Chez un gérant',
            self::STATUT_MAINTENANCE, self::STATUT_EN_PANNE => 'En Panne',
            self::STATUT_DISPONIBLE => 'Disponible',
            self::STATUT_CEDEE => 'Cédée',
            default => $this->libelleStatut(),
        };
    }

    public function classeStatutDocuments(): string
    {
        return match ($this->statut) {
            self::STATUT_EN_LOCATION => 'bg-success',
            self::STATUT_CHEZ_GERANT => 'bg-info',
            self::STATUT_MAINTENANCE, self::STATUT_EN_PANNE => 'bg-danger',
            self::STATUT_DISPONIBLE => 'bg-primary',
            default => 'bg-secondary',
        };
    }

    public function couleurStatut(): string
    {
        return match ($this->statut) {
            self::STATUT_DISPONIBLE => 'success',
            self::STATUT_EN_LOCATION => 'primary',
            self::STATUT_CHEZ_GERANT => 'info',
            self::STATUT_EN_PANNE => 'danger',
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

    /**
     * Enregistre les documents fournis. Un type absent du tableau n'est pas modifié.
     * Un type présent mais vide (ni dates, ni numéro, ni fichier) est supprimé.
     *
     * @param  array<string, array{date_debut?: ?string, date_fin?: ?string, numero?: ?string, fichier?: ?UploadedFile}>  $saisies
     */
    public function enregistrerDocuments(array $saisies): void
    {
        $nouveauxChemins = [];
        $anciensChemins = [];

        try {
            foreach ($saisies as $type => $saisie) {
                if (! array_key_exists($type, MotoDocument::TYPES)) {
                    continue;
                }

                $existant = $this->documents()->where('type', $type)->first();
                $fichier = $saisie['fichier'] ?? null;

                $dateDebut = array_key_exists('date_debut', $saisie)
                    ? ($saisie['date_debut'] ?: null)
                    : $existant?->date_debut?->toDateString();
                $dateFin = array_key_exists('date_fin', $saisie)
                    ? ($saisie['date_fin'] ?: null)
                    : $existant?->date_fin?->toDateString();
                $numero = array_key_exists('numero', $saisie)
                    ? ($saisie['numero'] ?: null)
                    : $existant?->numero;

                $meta = [];
                if ($fichier instanceof UploadedFile) {
                    $chemin = $this->stockerDocument($fichier, $type);
                    $nouveauxChemins[] = $chemin;
                    $meta = [
                        'chemin' => $chemin,
                        'nom_original' => mb_substr($fichier->getClientOriginalName(), 0, 255),
                        'mime' => $fichier->getMimeType(),
                        'taille' => $fichier->getSize(),
                    ];
                    if ($existant?->chemin) {
                        $anciensChemins[] = $existant->chemin;
                    }
                }

                $cheminFinal = $meta['chemin'] ?? $existant?->chemin;
                $vide = $dateDebut === null && $dateFin === null && $numero === null && blank($cheminFinal);

                if ($vide) {
                    if ($existant) {
                        if ($existant->chemin) {
                            $anciensChemins[] = $existant->chemin;
                        }
                        $existant->delete();
                    }

                    continue;
                }

                $this->documents()->updateOrCreate(['type' => $type], array_merge([
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin,
                    'numero' => $numero,
                ], $meta));
            }
        } catch (\Throwable $exception) {
            if ($nouveauxChemins !== []) {
                rescue(fn () => Storage::disk(self::DISQUE)->delete($nouveauxChemins), report: false);
            }

            throw $exception;
        }

        if ($anciensChemins !== []) {
            rescue(fn () => Storage::disk(self::DISQUE)->delete($anciensChemins), report: false);
        }

        $this->unsetRelation('documents');
    }

    private function stockerDocument(UploadedFile $fichier, string $type): string
    {
        $extension = strtolower($fichier->guessExtension() ?: $fichier->getClientOriginalExtension() ?: 'bin');
        $nom = $type.'-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$extension;

        $chemin = rescue(
            fn () => Storage::disk(self::DISQUE)->putFileAs('location-vente/motos/'.$this->id.'/documents', $fichier, $nom),
            false,
            report: false
        );

        if (! is_string($chemin) || $chemin === '') {
            throw ValidationException::withMessages([
                "documents.{$type}.fichier" => MotoDocument::TYPES[$type].' : l\'envoi vers le stockage Cloudflare R2 a échoué. Réessayez.',
            ]);
        }

        return $chemin;
    }
}
