<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UppercasesAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipForm extends Model
{
    use HasFactory, UppercasesAttributes;

    /** Free-text attributes stored in uppercase (see UppercasesAttributes). */
    protected array $uppercase = ['name', 'lastname', 'address', 'seller'];

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
