<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateNote extends Model
{
    protected $fillable = ['affiliate_id', 'user_id', 'body'];

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
