<?php

declare(strict_types=1);

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

    // Relationship: the city belongs to a department
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // Relationship: a city has many admins
    public function admins()
    {
        return $this->hasMany(Admin::class);
    }

    // Relationship: a city has many users
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // Relationship: a city has many counselors
    public function counselors()
    {
        return $this->hasMany(Counselor::class);
    }

    // Relationship: a city has many affiliates
    public function affiliates()
    {
        return $this->hasMany(Affiliate::class);
    }

    // Relationship: a city has many membership forms
    public function membershipForms()
    {
        return $this->hasMany(MembershipForm::class);
    }

    // Relationship: a city has many contact messages
    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    // Relationship: a city has many appointments
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
