<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProperty extends Model
{
    use HasFactory;

    protected $fillable = ['module_id', 'counselor_id', 'property'];

    // Relationship with Modules
    public function modules()
    {
        return $this->belongsTo(Module::class);
    }

    // Relationship with Counselors
    public function counselor()
    {
        return $this->belongsTo(Counselor::class);
    }
}
