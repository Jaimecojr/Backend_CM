<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    // Relación: un departamento tiene muchas ciudades
    public function cities()
    {
        return $this->hasMany(City::class);
    }
}
