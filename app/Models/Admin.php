<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'lastname',
        'phone',
        'login',
        'pass',
        'type',
        'status',
        'city_id',
    ];

    protected $hidden = [
        'pass',
    ];


    // Relación con City
    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
