<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'action', 
        'table', 
        'table_id'
    ];
}
