<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementCommission extends Model
{
    protected $table = 'paiements_commissions';

    public $timestamps = false;

    protected $fillable = [
        'commercial_id',
        'periode',
        'montant',
        'date_paiement',
    ];

    protected function casts(): array
    {
        return [
            'periode' => 'date',
            'date_paiement' => 'date',
        ];
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'commercial_id');
    }
}
