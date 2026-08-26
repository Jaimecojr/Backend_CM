<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agreement extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'amount',
        'state',
        'city_id',
    ];

    // Relationship with city
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // Relationship: an agreement has many affiliates
    public function affiliates()
    {
        return $this->hasMany(Affiliate::class);
    }

}
