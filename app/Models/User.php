<?php

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
        'contacto',
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
            'state' => 'boolean',
        ];
    }

    public function username()
    {
        return 'user';
    }

    // Relación con City
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // Relación: un franquicia tiene muchos vendedores
    public function counselors()
    {
        return $this->hasMany(Counselor::class);
    }

    // Relación: un franquicia / usuario tiene muchos afiliados
    public function affiliates()
    {
        return $this->hasMany(Affiliate::class);
    }

    // Relación: un franquicia / usuario tiene muchas citas
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
