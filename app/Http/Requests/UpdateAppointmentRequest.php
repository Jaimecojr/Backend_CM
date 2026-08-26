<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sin override de failedValidation(): ver nota en StoreAppointmentRequest.
 * Se preserva el contrato de API original (422, ValidationException por
 * defecto de Laravel) porque este endpoint tiene tráfico real desde
 * frontend-cm y ese era su comportamiento antes de la Tarea 15.
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
            'value'     => 'required|numeric|min:10000',
            'type'      => 'required|in:1,2',
            'name'      => 'required|string|max:255',
            'user_id'   => 'required|exists:users,id',
        ];
    }
}
