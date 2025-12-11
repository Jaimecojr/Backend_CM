<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'state',
    ];

    // Relaciones
    // Relación: una especialidad tiene muchos doctores
    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }
}
