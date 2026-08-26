<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sin override de failedValidation(): a diferencia de Affiliate/User, este
 * endpoint ya tenía tráfico real desde frontend-cm antes de la Tarea 15, y su
 * comportamiento original (Validator::make() manual) devolvía 422 en fallos
 * de validación — no 400 como el resto de la app. Se preserva ese contrato
 * de API dejando que FormRequest use el comportamiento por defecto de
 * Laravel (ValidationException -> 422), en vez de alinearlo con la
 * convención de 400 usada en los demás módulos. Ver task-15-report.md.
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
            'value'     => 'required|numeric|min:10000',
            'type'      => 'required|in:1,2',
            'name'      => 'required|string|max:255',
            'user_id'   => 'required|exists:users,id',
        ];
    }
}
