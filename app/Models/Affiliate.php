<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    use HasFactory;

    protected $fillable = [
        'counselor_id',
        'contract_code',
        'name',
        'lastname',
        'bithdate',
        'id_card',
        'phone',
        'movil',
        'address',
        'city_id',
        'email',
        'validity',
        'value_sale',
        'counselor',
        'agreement',
        'agreement_id',
        'balance',
        'comission',
        'payment_commission',
        'company',
        'photo',
        'photo_rename',
        'validity_end',
        'stade',
        'carnet',
        'today',
        'state',
        'fran_code',
        'user_id',
        'sale_date',
    ];

    // Relaciones
    // El afiliado pertenece a un vendedor
    public function counselor()
    {
        return $this->belongsTo(Counselor::class);
    }

    // El afiliado pertenece a una ciudad
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // El afiliado tiene un convenio
    public function agreement()
    {
        return $this->belongsTo(Agreement::class);
    }

    // El afiliado tiene una franquicia / usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relación: un Afiliado tiene muchos beneficiarios
    public function beneficiaries()
    {
        return $this->hasMany(Beneficiary::class);
    }

    // Relación: un Afiliado tiene muchos renovaciones
    public function renovations()
    {
        return $this->hasMany(Renovation::class);
    }

}
