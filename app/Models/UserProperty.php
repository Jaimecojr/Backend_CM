<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProperty extends Model
{
    use HasFactory;

    protected $fillable = ['module_id', 'counselor_id', 'property'];

    // Relación con Modulos
    public function modules()
    {
        return $this->belongsTo(Module::class);
    }

    // Relación con Vendedores
    public function counselor()
    {
        return $this->belongsTo(Counselor::class);
    }
}
