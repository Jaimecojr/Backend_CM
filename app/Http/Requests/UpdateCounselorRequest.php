<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Controllers\CounselorController;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCounselorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Route::apiResource('counselors', ...) exposes the id as {counselor};
        // used to exclude the record itself from the unique rules, same as
        // the original Validator::make() ("unique:counselors,id_card," . $id).
        $id = $this->route('counselor');

        return [
            'name'           => 'sometimes|required|string|max:255',
            'lastname'       => 'sometimes|required|string|max:255',
            'id_card'        => 'sometimes|required|regex:/^\d+$/|max:100|unique:counselors,id_card,' . $id,
            'address'        => 'nullable|string|max:255',
            'date_admission' => 'nullable|date',
            'type_contra'    => 'sometimes|required|in:' . implode(',', CounselorController::typeContraValues()),
            'email'          => 'nullable|email|max:255|unique:counselors,email,' . $id,
            'password'       => 'nullable|string|min:6',
            'rol'            => 'nullable|numeric',
            'phone'          => 'nullable|string|max:255',
            'movil'          => 'nullable|digits:10',
            'state'          => 'sometimes|required|in:1,2',
            'city_id'        => 'sometimes|required|exists:cities,id',
            'user_id'        => 'sometimes|required|exists:users,id',
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
