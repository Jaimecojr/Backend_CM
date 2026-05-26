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
        'state',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function membershipFormBeneficiaries()
    {
        return $this->hasMany(MembershipFormBeneficiary::class);
    }
}
