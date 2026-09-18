<?php

declare(strict_types=1);

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
        'email',
        'phone',
        'movil',
        'address',
        'secretary_name',
        'value_agreement',
        'city_id'
    ];

    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
