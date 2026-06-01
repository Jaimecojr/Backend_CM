<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agreement extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'amount',
        'state',
        'city_id',
    ];

    // Relación con ciudad
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // Relación: un convenio tiene muchos afiliados
    public function affiliates()
    {
        return $this->hasMany(Affiliate::class);
    }

}
