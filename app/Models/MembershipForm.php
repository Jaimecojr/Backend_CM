<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'lastname',
        'id_card',
        'phone',
        'email',
        'bithdate',
        'address',
        'city_id',
        'date',
        'seller',
        'add',
    ];

    // Relaciones
    // un formulario una ciudad
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // Relación: un Formulario tiene muchos beneficiarios
    public function membershipFormBeneficiaries()
    {
        return $this->hasMany(MembershipFormBeneficiary::class);
    }
}
