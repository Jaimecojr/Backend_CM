<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateAffiliateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'counselor_id'       => 'nullable|exists:counselors,id',
            'contract_code'      => 'nullable|string|max:100',
            'name'               => 'sometimes|required|string|max:100',
            'lastname'           => 'sometimes|required|string|max:100',
            'bithdate'           => 'nullable|date',
            'id_card'            => 'sometimes|required|string|max:50',
            'phone'              => 'nullable|string|max:50',
            'movil'              => 'sometimes|required|digits:10',
            'address'            => 'nullable|string|max:150',
            'city_id'            => 'nullable|exists:cities,id',
            'email'              => 'nullable|email|max:100',
            'validity'           => 'nullable|date',
            'agreement_id'       => 'nullable|exists:agreements,id',
            'company'            => 'nullable|string|max:150',
            'photo'              => 'nullable|string',
            'photo_rename'       => 'nullable|string',
            'validity_end'       => 'sometimes|required|date',
            'stade'              => 'nullable|integer',
            'carnet'             => 'nullable|in:si,no',
            'state'              => 'nullable|integer',
            'user_id'            => 'nullable|exists:users,id',
            'payment_date'       => 'nullable|date',
            'value'              => 'nullable|integer',
            'balance'            => 'nullable|integer',
            'commission'         => 'nullable|integer',
            'payment_commission' => 'nullable|in:si,no',
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
