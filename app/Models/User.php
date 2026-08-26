<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nit',
        'name',
        'contact',
        'phone',
        'movil',
        'address',
        'date_afi',
        'email',
        'user',
        'password',
        'state',
        'city_id',
        'type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'state' => 'integer',
            'type' => 'integer',
        ];
    }

    public function username()
    {
        return 'user';
    }

    // Super admin: the only role with access to global metrics, agreement/note
    // management, and no "only see my own records" restriction.
    public function isSuperAdmin(): bool
    {
        return $this->type === 1;
    }

    // Relationship with City
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // Relationship: a franchise has many counselors
    public function counselors()
    {
        return $this->hasMany(Counselor::class);
    }

    // Relationship: a franchise/user has many affiliates
    public function affiliates()
    {
        return $this->hasMany(Affiliate::class);
    }

    // Relationship: a franchise/user has many appointments
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
