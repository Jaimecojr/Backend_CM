<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nit' => 'required|regex:/^\d+$/|max:100|unique:users,nit',
            'name' => 'required|string|max:100',
            'contact' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'movil' => 'nullable|digits:10',
            'address' => 'nullable|string|max:150',
            'date_afi' => 'nullable|date',
            'email' => 'required|email|unique:users,email',
            'user' => 'required|string|max:100|unique:users,user',
            'password' => 'required|string|min:6',
            'state' => 'nullable|in:1,2',
            'city_id' => 'required|exists:cities,id',
            'type' => 'nullable|in:1,2,3',
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
