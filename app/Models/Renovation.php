<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Renovation extends Model
{
    use HasFactory;

    protected $fillable = [
        'date_ini',
        'date_end',
        'value',
        'affiliate_id',
    ];

    // Relaciones
    // una renovacion un afiliado
    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }
}
