<?php

declare(strict_types=1);

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

    // Relationships
    // Relationship: a specialty has many doctors
    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }
}
