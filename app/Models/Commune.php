<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commune extends Model
{
    protected $table = 'communes';

    protected $primaryKey = 'commune_id';

    public $timestamps = false;

    protected $fillable = [
        'nom_commune',
    ];

    public function boutiques(): HasMany
    {
        return $this->hasMany(Boutique::class, 'commune_id', 'commune_id');
    }
}
