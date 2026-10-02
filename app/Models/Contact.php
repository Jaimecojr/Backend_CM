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

    /**
     * Free text written by people (contact messages, notes about an affiliate) can carry health or
     * personal details, which Colombian law (Ley 1581) treats as sensitive data: it is stored
     * encrypted at rest. It is never searched with SQL, so encryption costs nothing functionally.
     */
    protected function casts(): array
    {
        return [
            'comment' => 'encrypted',
        ];
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
