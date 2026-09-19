<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UppercasesAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory, UppercasesAttributes;

    /** Free-text attributes stored in uppercase (see UppercasesAttributes). */
    protected array $uppercase = ['name', 'subject', 'comment'];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'city_id',
        'subject',
        'comment',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
