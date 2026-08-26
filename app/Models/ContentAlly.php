<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentAlly extends Model
{
    use HasFactory;

    protected $fillable = [
        'image',
        'image_filename',
        'url',
        'position',
    ];
}
