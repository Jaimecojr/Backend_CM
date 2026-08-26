<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * No failedValidation() override: see note in StoreAppointmentRequest.
 * The original API contract is preserved (422, Laravel's default
 * ValidationException) because this endpoint has real traffic from
 * frontend-cm and that was its behavior before Task 15.
 */
class UpdateAppointmentRequest extends FormRequest
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
