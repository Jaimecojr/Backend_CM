<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipFormBeneficiary extends Model
{
    use HasFactory;

    protected $fillable = [
        'membership_form_id',
        'name',
    ];

    // Relaciones
    // un Beneficiario tiene un formularios
    public function membershipForm()
    {
        return $this->belongsTo(MembershipForm::class);
    }
}
