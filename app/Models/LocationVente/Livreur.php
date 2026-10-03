<?php

namespace App\Models\LocationVente;

use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Livreur extends Model
{
    public const DISQUE = 'r2';

    public const KYC_INCOMPLET = 'incomplet';

    public const KYC_EN_ATTENTE = 'en_attente';

    public const KYC_VALIDE = 'valide';

    public const KYC_REJETE = 'rejete';

    public const KYC_STATUTS = [
        self::KYC_INCOMPLET => ['KYC incomplet', 'secondary'],
        self::KYC_EN_ATTENTE => ['KYC à vérifier', 'warning'],
        self::KYC_VALIDE => ['KYC accepté', 'success'],
        self::KYC_REJETE => ['KYC rejeté', 'danger'],
    ];

    public const TYPES_PIECE = [
        'CNI' => "Carte nationale d'identité",
        'Passeport' => 'Passeport',
        'Carte consulaire' => 'Carte consulaire',
        'Attestation' => "Attestation d'identité",
        'Carte de séjour' => 'Carte de séjour',
    ];

    public const CATEGORIES_PERMIS = [
        'TOUTES' => 'Toutes les catégories',
        'A' => 'A',
        'A1' => 'A1',
        'B' => 'B',
        'C' => 'C',
        'D' => 'D',
        'E' => 'E',
    ];

    protected $table = 'location_vente_livreurs';

    protected $fillable = [
        'code',
        'photo',
        'nom',
        'prenoms',
        'date_naissance',
        'contact',
        'contact_urgence',
        'adresse',
        'type_piece',
        'numero_piece',
        'date_expiration_piece',
        'numero_permis',
        'categorie_permis',
        'date_expiration_permis',
        'statut',
        'kyc_statut',
        'kyc_verifie_le',
        'kyc_verifie_par',
        'kyc_motif_rejet',
        'notes',
    ];

    protected $attributes = [
        'statut' => false,
    ];

    protected function casts(): array
    {
        return [
            'statut' => 'boolean',
            'date_naissance' => 'date',
            'date_expiration_piece' => 'date',
            'date_expiration_permis' => 'date',
            'kyc_verifie_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Livreur $livreur) {
            if (! $livreur->code) {
                $livreur->forceFill(['code' => 'LVR-'.str_pad((string) $livreur->id, 4, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });

        static::deleting(function (Livreur $livreur) {
            $chemins = $livreur->documents()->pluck('chemin')->push($livreur->photo)->filter()->all();
            rescue(fn () => Storage::disk(self::DISQUE)->delete($chemins), report: false);
        });
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class, 'livreur_id');
    }

    public function contratEnCours(): HasOne
    {
        return $this->hasOne(Contrat::class, 'livreur_id')->where('statut', Contrat::STATUT_EN_COURS);
    }

    public function scopePeuventRecevoirUneMoto($query)
    {
        return $query->where('statut', true)->doesntHave('contratEnCours')->orderBy('nom')->orderBy('prenoms');
    }

    public function peutRecevoirUneMoto(): bool
    {
        return $this->statut && ! $this->contratEnCours()->exists();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LivreurDocument::class, 'livreur_id');
    }

    public function verificateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'kyc_verifie_par');
    }

    public function nomComplet(): string
    {
        return trim($this->prenoms.' '.$this->nom);
    }

    public function initiales(): string
    {
        return mb_strtoupper(mb_substr((string) $this->prenoms, 0, 1).mb_substr((string) $this->nom, 0, 1));
    }

    public function enregistrerPhoto(UploadedFile $fichier): void
    {
        $ancienne = $this->photo;
        $this->update(['photo' => $this->stocker($fichier, 'photo')]);
        $this->supprimerFichier($ancienne);
        $this->recalculerKyc(documentModifie: true);
    }

    public function enregistrerDocument(string $type, UploadedFile $fichier): LivreurDocument
    {
        $existant = $this->documents()->where('type', $type)->first();
        $chemin = $this->stocker($fichier, $type);

        $document = $this->documents()->updateOrCreate(['type' => $type], [
            'chemin' => $chemin,
            'nom_original' => mb_substr($fichier->getClientOriginalName(), 0, 255),
            'mime' => $fichier->getMimeType(),
            'taille' => $fichier->getSize(),
        ]);

        if ($existant && $existant->chemin !== $chemin) {
            $this->supprimerFichier($existant->chemin);
        }

        $this->recalculerKyc(documentModifie: true);

        return $document;
    }

    public function supprimerDocument(LivreurDocument $document): void
    {
        $this->supprimerFichier($document->chemin);
        $document->delete();
        $this->recalculerKyc(documentModifie: true);
    }

    /**
     * @return list<string>
     */
    public function elementsKycManquants(): array
    {
        $presents = $this->relationLoaded('documents')
            ? $this->documents->pluck('type')->all()
            : $this->documents()->pluck('type')->all();

        $manquants = [];

        if (! $this->photo) {
            $manquants[] = 'Photo du livreur';
        }
        if (! $this->numero_piece) {
            $manquants[] = "Numéro de la pièce d'identité";
        }
        if (! $this->numero_permis) {
            $manquants[] = 'Numéro du permis de conduire';
        }
        foreach (LivreurDocument::OBLIGATOIRES as $type) {
            if (! in_array($type, $presents, true)) {
                $manquants[] = LivreurDocument::TYPES[$type];
            }
        }

        return $manquants;
    }

    public function kycAccepte(): bool
    {
        return $this->kyc_statut === self::KYC_VALIDE;
    }

    /**
     * Passe le dossier en « incomplet » ou « à vérifier » selon les pièces fournies.
     * Toute modification d'un document validé ou rejeté impose une nouvelle vérification,
     * et le livreur redevient inactif tant que le KYC n'est pas de nouveau accepté.
     */
    public function recalculerKyc(bool $documentModifie = false): void
    {
        $this->unsetRelation('documents');
        $decide = in_array($this->kyc_statut, [self::KYC_VALIDE, self::KYC_REJETE], true);

        if ($decide && ! $documentModifie) {
            return;
        }

        $statut = $this->elementsKycManquants() === [] ? self::KYC_EN_ATTENTE : self::KYC_INCOMPLET;

        if ($statut !== $this->kyc_statut || $decide) {
            $this->update([
                'kyc_statut' => $statut,
                'kyc_verifie_le' => null,
                'kyc_verifie_par' => null,
                'kyc_motif_rejet' => null,
                'statut' => false,
            ]);
        }
    }

    public function libelleKyc(): string
    {
        return self::KYC_STATUTS[$this->kyc_statut][0] ?? $this->kyc_statut;
    }

    public function libelleCategoriePermis(): ?string
    {
        return self::CATEGORIES_PERMIS[$this->categorie_permis] ?? $this->categorie_permis;
    }

    public function couleurKyc(): string
    {
        return self::KYC_STATUTS[$this->kyc_statut][1] ?? 'secondary';
    }

    public function pieceExpiree(): bool
    {
        return $this->date_expiration_piece !== null && $this->date_expiration_piece->isPast();
    }

    public function permisExpire(): bool
    {
        return $this->date_expiration_permis !== null && $this->date_expiration_permis->isPast();
    }

    private function stocker(UploadedFile $fichier, string $prefixe): string
    {
        $extension = strtolower($fichier->guessExtension() ?: $fichier->getClientOriginalExtension() ?: 'bin');
        $nom = $prefixe.'-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$extension;

        $chemin = rescue(
            fn () => Storage::disk(self::DISQUE)->putFileAs('location-vente/livreurs/'.$this->id, $fichier, $nom),
            false,
            report: false
        );

        if (! is_string($chemin) || $chemin === '') {
            throw ValidationException::withMessages([
                'fichier' => "L'envoi du fichier vers le stockage Cloudflare R2 a échoué. Réessayez.",
            ]);
        }

        return $chemin;
    }

    private function supprimerFichier(?string $chemin): void
    {
        if ($chemin) {
            rescue(fn () => Storage::disk(self::DISQUE)->delete($chemin), report: false);
        }
    }
}
