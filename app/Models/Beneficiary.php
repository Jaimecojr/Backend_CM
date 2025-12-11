<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'name',
        'id_card',
        'bithdate',
    ];

    // Relaciones
    // El beneficiario pertenece a un afiliado
    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }
}
