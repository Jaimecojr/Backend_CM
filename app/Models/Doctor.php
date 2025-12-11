<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'specialty_id',
        'state',
        'name',
        'lastname',
        'phone',
        'movil',
        'address',
        'secretary_name',
        'value_agreement',
        'state',
        'city_id'
    ];

    // Relaciones
    // un doctor una especialidad
    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }

    // un doctor puede tener muchas citas
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
