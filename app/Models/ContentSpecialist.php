<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentSpecialist extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'specialty',
        'photo',
        'photo_filename',
        'position',
    ];
}
