<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'afi_code',
        'doctor_id',
        'date',
        'hour',
        'address',
        'city_id',
        'phone',
        'value',
        'type',
        'name',
        'user_id',
    ];

    /**
     * Cast explicitly to int: without this, PDO can return numeric columns
     * as strings depending on driver/config, which throws a TypeError under
     * declare(strict_types=1) at any strict int|float call site (e.g.
     * number_format() in AppointmentController) and breaks the `type === 1`
     * comparisons used to resolve `owner`.
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'type'  => 'integer',
        ];
    }

    // Relationships
    // The appointment belongs to a doctor
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    // The appointment belongs to a city
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // The appointment belongs to a franchise / user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // afi_code points to the primary affiliate (type = 1)
    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class, 'afi_code');
    }

    // afi_code points to the beneficiary (type = 2)
    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class, 'afi_code');
    }
}
