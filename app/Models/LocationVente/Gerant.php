<?php

namespace App\Models\LocationVente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Gerant extends Model
{
    public const DISQUE = 'r2';

    protected $table = 'location_vente_gerants';

    protected $fillable = [
        'code',
        'nom',
        'prenoms',
        'contact',
        'photo',
        'adresse',
        'notes',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'statut' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Gerant $gerant) {
            if (blank($gerant->code)) {
                $gerant->code = static::prochainCode();
            }
        });

        static::deleting(function (Gerant $gerant) {
            if ($gerant->photo) {
                rescue(fn () => Storage::disk(self::DISQUE)->delete($gerant->photo), report: false);
            }
        });
    }

    public static function prochainCode(): string
    {
        $dernier = static::query()->orderByDesc('id')->value('code');
        $numero = 1;
        if (is_string($dernier) && preg_match('/(\d+)$/', $dernier, $matches)) {
            $numero = (int) $matches[1] + 1;
        }

        return 'GER-'.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }

    public function motos(): HasMany
    {
        return $this->hasMany(Moto::class, 'gerant_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(GerantPaiement::class, 'gerant_id');
    }

    public function nomComplet(): string
    {
        return trim($this->prenoms.' '.$this->nom) ?: $this->nom;
    }

    public function estActif(): bool
    {
        return (bool) $this->statut;
    }

    public function enregistrerPhoto(UploadedFile $fichier): void
    {
        $ancienne = $this->photo;
        $this->update(['photo' => $this->stocker($fichier)]);
        if ($ancienne) {
            rescue(fn () => Storage::disk(self::DISQUE)->delete($ancienne), report: false);
        }
    }

    private function stocker(UploadedFile $fichier): string
    {
        $extension = strtolower($fichier->guessExtension() ?: $fichier->getClientOriginalExtension() ?: 'jpg');
        $nom = 'photo-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$extension;

        $chemin = rescue(
            fn () => Storage::disk(self::DISQUE)->putFileAs('location-vente/gerants/'.$this->id, $fichier, $nom),
            false,
            report: false
        );

        if (! is_string($chemin) || $chemin === '') {
            throw ValidationException::withMessages([
                'photo' => "L'envoi de la photo vers le stockage a échoué. Réessayez.",
            ]);
        }

        return $chemin;
    }
}
