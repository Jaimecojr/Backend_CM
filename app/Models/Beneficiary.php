<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UppercasesAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    use HasFactory, UppercasesAttributes;

    /** Free-text attributes stored in uppercase (see UppercasesAttributes). */
    protected array $uppercase = ['name'];

    protected $fillable = [
        'affiliate_id',
        'name',
        'id_card',
        'bithdate',
    ];

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }
}
