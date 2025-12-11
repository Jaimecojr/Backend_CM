<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'wa_api_version',
        'wa_phone_number_id',
        'wa_bearer_token',
        'wa_template_name',
    ];
}
