<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoutLivraison extends Model
{
    protected $table = 'cout_livraison';

    public $timestamps = false;

    protected $fillable = [
        'cout_livraison',
    ];
}
