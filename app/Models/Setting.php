<?php

declare(strict_types=1);

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
        'wa_appointment_template_name',
    ];

    /**
     * The Meta token can send WhatsApp messages as the company: it is never returned to the
     * browser (the panel only learns whether one is set) and is stored encrypted with APP_KEY, so
     * a database dump or a SQL error written to the log doesn't expose it.
     */
    protected $hidden = ['wa_bearer_token'];

    protected $appends = ['wa_bearer_token_set'];

    protected function casts(): array
    {
        return [
            'wa_bearer_token' => 'encrypted',
        ];
    }

    /**
     * Lets the panel show "token configured" without ever receiving the token itself.
     */
    public function getWaBearerTokenSetAttribute(): bool
    {
        return filled($this->wa_bearer_token);
    }
}
