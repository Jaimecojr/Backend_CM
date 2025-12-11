<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Counselor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'lastname',
        'id_card',
        'address',
        'date_admission',
        'type_contra',
        'email',
        'password',
        'rol',
        'phone',
        'movil',
        'state',
        'city_id',
        'user_id',
    ];

    // Relación con City
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // Relación con User / franquicia
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relación: un vendedor tiene muchas afiliados
    public function affiliates()
    {
        return $this->hasMany(Affiliate::class);
    }
}
