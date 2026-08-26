<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Ruta apiResource('users', ...) expone el id como {user}; se usa para
        // excluir el propio registro en las reglas `unique`, igual que el
        // Validator::make() original ("unique:users,nit," . $id).
        $id = $this->route('user');

        return [
            'nit' => 'nullable|regex:/^\d+$/|max:100|unique:users,nit,' . $id,
            'name' => 'nullable|string|max:100',
            'contact' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'movil' => 'nullable|digits:10',
            'address' => 'nullable|string|max:150',
            'date_afi' => 'nullable|date',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'user' => 'nullable|string|max:100|unique:users,user,' . $id,
            'password' => 'nullable|string|min:6',
            'state' => 'nullable|in:1,2',
            'city_id' => 'nullable|exists:cities,id',
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
