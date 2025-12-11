<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'afi_code',
        'doctor_id',
        'date',
        'hour',
        'address',
        'city_id',
        'phone',
        'value',
        'type',
        'name',
        'user_id',
    ];

    //Relaciones
    //La cita pertenece a un doctor
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    // La cita pertenece a una ciudad
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // La cita pertenece a una franquicia / usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
