<?php

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
