<?php

namespace App\Models\LocationVente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MotoDocument extends Model
{
    public const TYPE_VIGNETTE = 'vignette';

    public const TYPE_ASSURANCE = 'assurance';

    public const TYPE_CARTE_GRISE = 'carte_grise';

    public const TYPES = [
        self::TYPE_VIGNETTE => 'Vignette',
        self::TYPE_ASSURANCE => 'Assurance',
        self::TYPE_CARTE_GRISE => 'Carte grise',
    ];

    /**
     * Documents dont la validité est une période.
     */
    public const AVEC_VALIDITE = [
        self::TYPE_VIGNETTE,
        self::TYPE_ASSURANCE,
    ];

    protected $table = 'location_vente_moto_documents';

    protected $fillable = [
        'moto_id',
        'type',
        'numero',
        'date_debut',
        'date_fin',
        'chemin',
        'nom_original',
        'mime',
        'taille',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'taille' => 'integer',
        ];
    }

    public function moto(): BelongsTo
    {
        return $this->belongsTo(Moto::class, 'moto_id');
    }

    public function libelle(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function aUneValidite(): bool
    {
        return in_array($this->type, self::AVEC_VALIDITE, true);
    }

    public function estExpire(): bool
    {
        return $this->date_fin !== null && $this->date_fin->toDateString() < now()->toDateString();
    }

    public function joursRestants(): ?int
    {
        if ($this->date_fin === null) {
            return null;
        }

        $fin = $this->date_fin->copy()->startOfDay();
        $aujourdhui = now()->startOfDay();

        if ($fin->lessThan($aujourdhui)) {
            return 0;
        }

        return (int) $aujourdhui->diffInDays($fin);
    }

    public function expireBientot(): bool
    {
        if ($this->date_fin === null || $this->estExpire()) {
            return false;
        }

        return $this->date_fin->toDateString() <= now()->addDays(30)->toDateString();
    }

    public function couleurValidite(): string
    {
        if ($this->estExpire()) {
            return 'danger';
        }

        if ($this->expireBientot()) {
            return 'warning';
        }

        if ($this->date_fin !== null) {
            return 'success';
        }

        return 'secondary';
    }

    public function estImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
