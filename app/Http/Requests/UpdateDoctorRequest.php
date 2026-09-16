<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => 'sometimes|required|string|max:255',
            'lastname'        => 'sometimes|required|string|max:255',
            'email'           => 'nullable|email|max:255',
            'specialty_id'    => 'sometimes|required|exists:specialties,id',
            'city_id'         => 'sometimes|required|exists:cities,id',
            'phone'           => 'sometimes|required|string|max:255',
            'movil'           => 'sometimes|required|digits:10',
            'address'         => 'sometimes|required|string|max:255',
            'secretary_name'  => 'sometimes|required|string|max:255',
            'value_agreement' => 'sometimes|required|numeric|min:10000',
            'state'           => 'sometimes|required|in:1,2',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación',
            'errors' => $validator->errors(),
        ], 400));
    }
}
