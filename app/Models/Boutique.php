<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Boutique extends Model
{
    protected $table = 'boutiques';

    public $timestamps = false;

    protected $fillable = [
        'nom',
        'logo',
        'type_articles',
        'statut',
        'latitude',
        'longitude',
        'commune_id',
        'commercial_id',
    ];

    protected function casts(): array
    {
        return [
            'statut' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class, 'commune_id', 'commune_id');
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'commercial_id');
    }

    public function scopeForCommercial($query, int $commercialId)
    {
        return $query->where('commercial_id', $commercialId);
    }

    public function utilisateurs(): HasMany
    {
        return $this->hasMany(Utilisateur::class, 'boutique_id');
    }

    public function gerant(): HasOne
    {
        return $this->hasOne(Utilisateur::class, 'boutique_id')->where('role', 'clients');
    }

    public function commandes(): HasManyThrough
    {
        return $this->hasManyThrough(
            Commande::class,
            Utilisateur::class,
            'boutique_id',
            'utilisateur_id'
        );
    }

    public function logoKey(): string
    {
        $key = $this->logo ?: 'boutiques/default_boutiques.png';

        return str_contains($key, '/') ? $key : 'boutiques/'.$key;
    }

    public function logoUrl(): string
    {
        $key = $this->logo ?: 'boutiques/default_boutiques.png';

        try {
            $disk = Storage::disk('r2');

            if (method_exists($disk, 'providesTemporaryUrls') && $disk->providesTemporaryUrls()) {
                return $disk->temporaryUrl($key, now()->addMinutes(30));
            }

            return $disk->url($key);
        } catch (\Throwable) {
            return route('boutiques.logo', $this);
        }
    }
}
