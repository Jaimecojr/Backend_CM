<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UppercasesAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Counselor extends Model
{
    use HasFactory, UppercasesAttributes;

    /** Free-text attributes stored in uppercase (see UppercasesAttributes). */
    protected array $uppercase = ['name', 'lastname', 'address'];

    protected $fillable = [
        'name',
        'lastname',
        'id_card',
        'address',
        'date_admission',
        'type_contra',
        'email',
        'password',
        'rol',
        'phone',
        'movil',
        'state',
        'city_id',
        'user_id',
    ];

    // Relationship with City
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // Relationship with User / franchise
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship: a counselor has many affiliates
    public function affiliates()
    {
        return $this->hasMany(Affiliate::class);
    }
}
