<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'name',
    ];

    // Relación: la ciudad pertenece a un departamento
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // Relación: un ciudad tiene muchos admins
    public function admins()
    {
        return $this->hasMany(Admin::class);
    }

    // Relación: un ciudad tiene muchos usuarios
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // Relación: un ciudad tiene muchos vendedores
    public function counselors()
    {
        return $this->hasMany(Counselor::class);
    }

    // Relación: un ciudad tiene muchos afiliados
    public function affiliates()
    {
        return $this->hasMany(Affiliate::class);
    }

    // Relación: un ciudad tiene muchos formularios de Afiliacion
    public function membershipForms()
    {
        return $this->hasMany(MembershipForm::class);
    }

    // Relación: un ciudad tiene muchos formularios de contacto
    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    // Relación: un ciudad tiene muchas citas
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
