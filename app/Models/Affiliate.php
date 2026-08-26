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

    // Relaciones
    // El afiliado pertenece a un vendedor
    public function counselor()
    {
        return $this->belongsTo(Counselor::class);
    }

    // El afiliado pertenece a una ciudad
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // El afiliado tiene un convenio
    public function agreement()
    {
        return $this->belongsTo(Agreement::class);
    }

    // El afiliado tiene una franquicia / usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relación: un Afiliado tiene muchos beneficiarios
    public function beneficiaries()
    {
        return $this->hasMany(Beneficiary::class);
    }

    // Relación: un Afiliado tiene muchos renovaciones
    public function renovations()
    {
        return $this->hasMany(Renovation::class);
    }

    // Relación: un Afiliado tiene muchas notas/observaciones
    public function notes()
    {
        return $this->hasMany(AffiliateNote::class);
    }

    // Scopes de vigencia
    // Activos cuya vigencia ya pasó — candidatos a inactivar (usado por el
    // comando affiliates:update-expired).
    public function scopeActivosVencidos($query)
    {
        return $query->where('stade', 1)->where('validity_end', '<', now()->toDateString());
    }

    // Activos que vencen exactamente hoy — alerta del dashboard para que
    // los asesores gestionen la renovación antes de que se inactiven.
    public function scopeActivosVencenHoy($query)
    {
        return $query->where('stade', 1)->where('validity_end', now()->toDateString());
    }

    // Ya inactivos y además con vigencia vencida — métrica de
    // dashboard/stats (distingue inactivos "por vencimiento" de inactivos
    // por baja manual).
    public function scopeInactivosPorVencimiento($query)
    {
        return $query->where('stade', 2)->where('validity_end', '<', now()->toDateString());
    }

}
