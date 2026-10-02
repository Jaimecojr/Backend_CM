<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UppercasesAttributes;
use Illuminate\Database\Eloquent\Model;

class AffiliateNote extends Model
{
    use UppercasesAttributes;

    /** Free-text attributes stored in uppercase (see UppercasesAttributes). */
    protected array $uppercase = ['body'];

    protected $fillable = ['affiliate_id', 'user_id', 'body'];

    /**
     * Free text written by people (contact messages, notes about an affiliate) can carry health or
     * personal details, which Colombian law (Ley 1581) treats as sensitive data: it is stored
     * encrypted at rest. It is never searched with SQL, so encryption costs nothing functionally.
     */
    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
        ];
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
