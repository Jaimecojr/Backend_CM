<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sin override de failedValidation(): a diferencia de Affiliate/User, este
 * endpoint ya devolvía 422 nativamente (comportamiento por defecto de
 * Laravel) antes de que este retrofit introdujera Form Requests, y ya tenía
 * tráfico real desde frontend-cm consumiéndolo. Alinearlo con la convención
 * de 400 usada en el resto de la app arriesgaba romper el manejo de errores
 * ya en producción de un endpoint en vivo por un beneficio puramente
 * cosmético, así que se dejó tal cual. Ver CLAUDE.md, sección "Código HTTP
 * en fallos de validación: split 400 / 422 entre módulos".
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
