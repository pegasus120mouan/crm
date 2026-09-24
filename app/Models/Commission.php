<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $table = 'commissions';

    public $timestamps = false;

    protected $fillable = [
        'taux',
    ];

    protected function casts(): array
    {
        return [
            'taux' => 'decimal:2',
        ];
    }

    public static function globale(): ?self
    {
        return static::query()->first();
    }

    public function montantPour(Commande $commande): int
    {
        return $this->montantPourBase((int) $commande->cout_livraison);
    }

    public function montantPourBase(int $baseLivraison): int
    {
        return (int) round($baseLivraison * ((float) $this->taux) / 100);
    }
}
