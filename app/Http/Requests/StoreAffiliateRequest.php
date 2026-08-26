<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreAffiliateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'counselor_id'       => 'required|exists:counselors,id',
            'contract_code'      => 'nullable|string|max:100',
            'name'               => 'required|string|max:100',
            'lastname'           => 'required|string|max:100',
            'bithdate'           => 'nullable|date',
            'id_card'            => 'required|string|max:50',
            'phone'              => 'nullable|string|max:50',
            'movil'              => 'required|digits:10',
            'address'            => 'nullable|string|max:150',
            'city_id'            => 'required|exists:cities,id',
            'email'              => 'nullable|email|max:100',
            'validity'           => 'required|date',
            'agreement_id'       => 'required|exists:agreements,id',
            'company'            => 'nullable|string|max:150',
            'photo'              => 'nullable|string',
            'photo_rename'       => 'nullable|string',
            'validity_end'       => 'required|date',
            'stade'              => 'nullable|integer',
            'carnet'             => 'required|in:si,no',
            'state'              => 'required|integer',
            'user_id'            => 'required|exists:users,id',
            'payment_date'       => 'required|date',
            'value'              => 'required|integer',
            'balance'            => 'required|integer',
            'commission'         => 'required|integer',
            'payment_commission' => 'required|in:si,no',
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
