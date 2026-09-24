<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Utilisateur extends Model
{
    protected $table = 'utilisateurs';

    public $timestamps = false;

    protected $fillable = [
        'nom',
        'prenoms',
        'contact',
        'login',
        'code_commercial',
        'avatar',
        'password',
        'code_pin',
        'api_token',
        'role',
        'boutique_id',
        'commercial_id',
        'statut_compte',
        'salaire_mensuel',
    ];

    protected $hidden = [
        'password',
        'code_pin',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'statut_compte' => 'boolean',
        ];
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class, 'boutique_id');
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(self::class, 'commercial_id');
    }

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class, 'utilisateur_id');
    }

    public function scopeForCommercial($query, int $commercialId)
    {
        return $query->where('commercial_id', $commercialId);
    }

    public function scopeClients($query)
    {
        return $query->where('role', 'clients');
    }

    public function scopeLivreurs($query)
    {
        return $query->where('role', 'livreur');
    }

    public function scopeCommerciaux($query)
    {
        return $query->where('role', 'commercial');
    }

    public function scopeActifs($query)
    {
        return $query->where('statut_compte', 1);
    }

    public function avatarKey(): string
    {
        $key = $this->avatar ?: 'utilisateurs/utilisateurs.png';

        if ($key === 'default.jpg') {
            $key = 'utilisateurs/utilisateurs.png';
        }

        return str_contains($key, '/') ? $key : 'utilisateurs/'.$key;
    }

    public function avatarUrl(): string
    {
        try {
            $disk = Storage::disk('r2');

            if (method_exists($disk, 'providesTemporaryUrls') && $disk->providesTemporaryUrls()) {
                return $disk->temporaryUrl($this->avatarKey(), now()->addMinutes(30));
            }

            return $disk->url($this->avatarKey());
        } catch (\Throwable) {
            return route('profil.avatar');
        }
    }
}
