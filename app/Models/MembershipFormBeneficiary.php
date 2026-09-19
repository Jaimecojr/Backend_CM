<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UppercasesAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipFormBeneficiary extends Model
{
    use HasFactory, UppercasesAttributes;

    /** Free-text attributes stored in uppercase (see UppercasesAttributes). */
    protected array $uppercase = ['name'];

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
