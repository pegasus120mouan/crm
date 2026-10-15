<?php

namespace App\Models\LocationVente;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrat extends Model
{
    public const STATUT_EN_COURS = 'en_cours';

    public const STATUT_SOLDE = 'solde';

    public const STATUT_RESILIE = 'resilie';

    public const STATUTS = [
        self::STATUT_EN_COURS => 'En cours',
        self::STATUT_SOLDE => 'Soldé – moto cédée',
        self::STATUT_RESILIE => 'Résilié',
    ];

    public const FREQUENCE_JOURNALIERE = 'journalier';

    public const FREQUENCE_HEBDOMADAIRE = 'hebdomadaire';

    public const FREQUENCES = [
        self::FREQUENCE_JOURNALIERE => 'Journalier (6 jours sur 7, sauf dimanche)',
        self::FREQUENCE_HEBDOMADAIRE => 'Hebdomadaire (paiement regroupé)',
    ];

    public const JOURS_TRAVAILLES_PAR_SEMAINE = 6;

    protected $table = 'location_vente_contrats';

    protected $fillable = [
        'reference',
        'livreur_id',
        'moto_id',
        'prix_achat',
        'cout_supplementaire',
        'marge',
        'prix_total',
        'apport',
        'montant_echeance',
        'frequence',
        'date_debut',
        'duree_mois',
        'date_fin',
        'statut',
        'date_solde',
        'date_resiliation',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'date_solde' => 'date',
            'date_resiliation' => 'date',
            'duree_mois' => 'integer',
            'prix_achat' => 'integer',
            'cout_supplementaire' => 'integer',
            'marge' => 'integer',
            'prix_total' => 'integer',
            'apport' => 'integer',
            'montant_echeance' => 'integer',
        ];
    }

    public function livreur(): BelongsTo
    {
        return $this->belongsTo(Livreur::class, 'livreur_id');
    }

    public function moto(): BelongsTo
    {
        return $this->belongsTo(Moto::class, 'moto_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class, 'contrat_id');
    }

    public function scopeEnCours($query)
    {
        return $query->where('statut', self::STATUT_EN_COURS);
    }

    public function attribuerReference(): void
    {
        if (! $this->reference) {
            $this->update(['reference' => 'LV-'.$this->date_debut->format('Y').'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT)]);
        }
    }

    public function montantPaye(): int
    {
        if (array_key_exists('paiements_sum_montant', $this->attributes)) {
            return (int) $this->attributes['paiements_sum_montant'];
        }

        if ($this->relationLoaded('paiements')) {
            return (int) $this->paiements->sum('montant');
        }

        return (int) $this->paiements()->sum('montant');
    }

    public function reste(): int
    {
        return max(0, $this->prix_total - $this->montantPaye());
    }

    public function progression(): int
    {
        return $this->prix_total > 0 ? (int) min(100, floor($this->montantPaye() * 100 / $this->prix_total)) : 0;
    }

    public function nombreEcheances(): int
    {
        $base = $this->prix_total - $this->apport;

        return $base > 0 && $this->montant_echeance > 0 ? (int) ceil($base / $this->montant_echeance) : 0;
    }

    /** Échéance = montant à verser (journalier) ou × 6 (hebdomadaire). */
    public static function montantEcheanceDepuisVersement(int $montantAVerser, string $frequence): int
    {
        return $frequence === self::FREQUENCE_HEBDOMADAIRE
            ? $montantAVerser * self::JOURS_TRAVAILLES_PAR_SEMAINE
            : $montantAVerser;
    }

    /** Montant à verser saisi manuellement (journalier), déduit de l'échéance stockée. */
    public function montantAVerser(): int
    {
        if ($this->frequence === self::FREQUENCE_HEBDOMADAIRE) {
            return (int) intdiv($this->montant_echeance, self::JOURS_TRAVAILLES_PAR_SEMAINE);
        }

        return (int) $this->montant_echeance;
    }

    /**
     * Nombre d'échéances sur la période [date début → date fin],
     * selon la fréquence (jours travaillés hors dimanche, ou semaines).
     */
    public static function nombreEcheancesSurPeriode(
        CarbonInterface|string $dateDebut,
        int $dureeMois,
        string $frequence
    ): int {
        if ($dureeMois < 1) {
            return 0;
        }

        $fin = static::dateFinDepuisDuree($dateDebut, $dureeMois);
        $reference = new static([
            'date_debut' => Carbon::parse($dateDebut)->toDateString(),
            'frequence' => $frequence,
            'montant_echeance' => 1,
            'prix_total' => PHP_INT_MAX,
            'apport' => 0,
        ]);

        $nombre = 0;
        for ($numero = 1; $numero <= 10000; $numero++) {
            if ($reference->dateEcheance($numero)->gt($fin)) {
                break;
            }
            $nombre = $numero;
        }

        return $nombre;
    }

    /** Prix d'acquisition de la moto (coût parc), distinct du prix de base du contrat. */
    public function prixAcquisitionMoto(): int
    {
        return (int) ($this->moto?->prix_achat ?? 0);
    }

    /**
     * Notre marge = total à rembourser − prix d'acquisition.
     * Répartie ensuite sur le nombre d'échéances.
     */
    public function notreMarge(): int
    {
        return $this->prix_total - $this->prixAcquisitionMoto();
    }

    public function notreMargeParEcheance(): int
    {
        $nombre = $this->nombreEcheances();

        return $nombre > 0 ? (int) intdiv($this->notreMarge(), $nombre) : $this->notreMarge();
    }

    public function dateEcheance(int $numero): Carbon
    {
        $date = Carbon::parse($this->date_debut)->startOfDay();

        if ($this->frequence === self::FREQUENCE_HEBDOMADAIRE) {
            return $date->addWeeks($numero);
        }

        if ($numero <= 0) {
            return $date;
        }

        // Toute période de 7 jours consécutifs contient exactement un dimanche.
        $semaines = intdiv($numero - 1, self::JOURS_TRAVAILLES_PAR_SEMAINE);
        $date->addWeeks($semaines);

        for ($restant = $numero - $semaines * self::JOURS_TRAVAILLES_PAR_SEMAINE; $restant > 0;) {
            $date->addDay();
            if (! $date->isSunday()) {
                $restant--;
            }
        }

        return $date;
    }

    public function dateFinPrevue(): Carbon
    {
        if ($this->date_fin) {
            return Carbon::parse($this->date_fin)->startOfDay();
        }

        return $this->dateEcheance($this->nombreEcheances());
    }

    public static function dateFinDepuisDuree(CarbonInterface|string $dateDebut, int $dureeMois): Carbon
    {
        return Carbon::parse($dateDebut)->startOfDay()->addMonthsNoOverflow($dureeMois);
    }

    public function cumulAttendu(int $numero): int
    {
        return min($this->prix_total, $this->apport + $numero * $this->montant_echeance);
    }

    public function echeancesEchues(?CarbonInterface $date = null): int
    {
        $date = Carbon::parse($date ?? Carbon::today())->startOfDay();
        $total = $this->nombreEcheances();
        $debut = Carbon::parse($this->date_debut)->startOfDay();

        if ($total === 0 || $date->lt($debut)) {
            return 0;
        }

        $estimation = (int) floor($this->frequence === self::FREQUENCE_HEBDOMADAIRE
            ? $debut->diffInWeeks($date)
            : $debut->diffInDays($date) * self::JOURS_TRAVAILLES_PAR_SEMAINE / 7);
        $echues = max(0, min($total, $estimation));

        while ($echues < $total && $this->dateEcheance($echues + 1)->lte($date)) {
            $echues++;
        }
        while ($echues > 0 && $this->dateEcheance($echues)->gt($date)) {
            $echues--;
        }

        return $echues;
    }

    public function montantAttendu(?CarbonInterface $date = null): int
    {
        return $this->cumulAttendu($this->echeancesEchues($date));
    }

    public function retard(?CarbonInterface $date = null): int
    {
        if ($this->statut !== self::STATUT_EN_COURS) {
            return 0;
        }

        return max(0, $this->montantAttendu($date) - $this->montantPaye());
    }

    public function echeancesEnRetard(?CarbonInterface $date = null): int
    {
        $retard = $this->retard($date);

        return $retard > 0 && $this->montant_echeance > 0 ? (int) ceil($retard / $this->montant_echeance) : 0;
    }

    public function prochaineEcheance(): ?Carbon
    {
        if ($this->statut !== self::STATUT_EN_COURS) {
            return null;
        }

        $paye = $this->montantPaye();

        for ($numero = 1; $numero <= $this->nombreEcheances(); $numero++) {
            if ($this->cumulAttendu($numero) > $paye) {
                return $this->dateEcheance($numero);
            }
        }

        return null;
    }

    /**
     * @return list<array{numero: int, date: Carbon, montant: int, cumul: int, statut: string}>
     */
    public function echeancier(): array
    {
        $paye = $this->montantPaye();
        $aujourdhui = Carbon::today();
        $lignes = [];

        for ($numero = 1; $numero <= $this->nombreEcheances(); $numero++) {
            $cumul = $this->cumulAttendu($numero);
            $cumulPrecedent = $this->cumulAttendu($numero - 1);
            $date = $this->dateEcheance($numero);

            $statut = match (true) {
                $paye >= $cumul => 'payee',
                $this->statut === self::STATUT_RESILIE => 'annulee',
                $paye > $cumulPrecedent && $date->gt($aujourdhui) => 'partielle',
                $date->lte($aujourdhui) => 'en_retard',
                default => 'a_venir',
            };

            $lignes[] = [
                'numero' => $numero,
                'date' => $date,
                'montant' => $cumul - $cumulPrecedent,
                'cumul' => $cumul,
                'statut' => $statut,
            ];
        }

        return $lignes;
    }

    /**
     * Solde le contrat (la moto sort du parc) quand tout est payé,
     * ou le rouvre si un paiement a été annulé.
     */
    public function synchroniserStatut(?CarbonInterface $dateSolde = null): void
    {
        $this->unsetRelation('paiements');
        unset($this->attributes['paiements_sum_montant']);
        $reste = $this->reste();

        if ($this->statut === self::STATUT_EN_COURS && $reste === 0) {
            $this->update([
                'statut' => self::STATUT_SOLDE,
                'date_solde' => Carbon::parse($dateSolde ?? Carbon::today())->toDateString(),
            ]);
            $this->moto()->update(['statut' => Moto::STATUT_CEDEE]);
        } elseif ($this->statut === self::STATUT_SOLDE && $reste > 0) {
            $this->update([
                'statut' => self::STATUT_EN_COURS,
                'date_solde' => null,
            ]);
            $this->moto()->update(['statut' => Moto::STATUT_EN_LOCATION]);
        }
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function couleurStatut(): string
    {
        return match ($this->statut) {
            self::STATUT_EN_COURS => $this->retard() > 0 ? 'danger' : 'primary',
            self::STATUT_SOLDE => 'success',
            default => 'secondary',
        };
    }

    public function uniteFrequence(): string
    {
        return $this->frequence === self::FREQUENCE_HEBDOMADAIRE ? 'semaine' : 'jour (sauf dimanche)';
    }

    public function libelleFrequence(): string
    {
        return self::FREQUENCES[$this->frequence] ?? $this->frequence;
    }
}
