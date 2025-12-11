<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'address',
        'city_id',
        'phone',
        'comment',
        'date',
    ];

    // Relaciones
    // un contacto tiene una ciudad
    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
