<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    use HasFactory;

    protected $fillable = [
        'counselor_id',
        'contract_code',
        'name',
        'lastname',
        'bithdate',
        'id_card',
        'phone',
        'movil',
        'address',
        'city_id',
        'email',
        'validity',
        'counselor',
        'agreement',
        'agreement_id',
        'company',
        'photo',
        'photo_rename',
        'validity_end',
        'payment_date',
        'value',
        'balance',
        'commission',
        'payment_commission',
        'stade',
        'carnet',
        'today',
        'state',
        'fran_code',
        'user_id',
    ];

    // Relationships
    // The affiliate belongs to a counselor (sales advisor)
    public function counselor()
    {
        return $this->belongsTo(Counselor::class);
    }

    // The affiliate belongs to a city
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // The affiliate has an agreement
    public function agreement()
    {
        return $this->belongsTo(Agreement::class);
    }

    // The affiliate belongs to a franchise / user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship: an affiliate has many beneficiaries
    public function beneficiaries()
    {
        return $this->hasMany(Beneficiary::class);
    }

    // Relationship: an affiliate has many renovations
    public function renovations()
    {
        return $this->hasMany(Renovation::class);
    }

    // Relationship: an affiliate has many notes/observations
    public function notes()
    {
        return $this->hasMany(AffiliateNote::class);
    }

    // Validity-period scopes
    // Active whose validity already expired — candidates to deactivate
    // (used by the affiliates:update-expired command).
    public function scopeActiveExpired($query)
    {
        return $query->where('stade', 1)->where('validity_end', '<', now()->toDateString());
    }

    // Active and expiring exactly today — dashboard alert so counselors
    // can handle the renewal before they get deactivated.
    public function scopeActiveExpiringToday($query)
    {
        return $query->where('stade', 1)->where('validity_end', now()->toDateString());
    }

    // Already inactive and with an expired validity — dashboard/stats
    // metric (distinguishes "inactive by expiry" from a manual deactivation).
    public function scopeInactiveByExpiry($query)
    {
        return $query->where('stade', 2)->where('validity_end', '<', now()->toDateString());
    }

}
