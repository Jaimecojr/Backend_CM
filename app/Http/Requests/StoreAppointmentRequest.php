<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * No failedValidation() override here: unlike Affiliate/User, this endpoint
 * already returned 422 natively (Laravel's default behavior) before this
 * retrofit introduced Form Requests, and already had real traffic from
 * frontend-cm consuming it. Aligning it with the 400 convention used
 * elsewhere in the app risked breaking a live endpoint's error handling
 * already in production for a purely cosmetic benefit, so it was left as-is.
 */
class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'afi_code'  => 'required|integer',
            'doctor_id' => 'required|exists:doctors,id',
            'date'      => 'required|date',
            'hour'      => 'required|string|max:10',
            'address'   => 'required|string|max:255',
            'city_id'   => 'required|exists:cities,id',
            'phone'     => 'nullable|string|max:255',
            'value'     => 'required|integer|min:10000',
            'type'      => 'required|in:1,2',
            'name'      => 'required|string|max:255',
            'user_id'   => 'required|exists:users,id',
        ];
    }
}
