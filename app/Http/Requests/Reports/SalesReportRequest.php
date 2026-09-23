<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SalesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from'         => 'nullable|date_format:Y-m-d',
            'to'           => 'nullable|date_format:Y-m-d',
            'franchise_id' => 'nullable|integer|exists:users,id',
            'counselor_id' => 'nullable|integer|exists:counselors,id',
            'per_page'     => ['nullable', 'regex:/^(all|[1-9][0-9]*)$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!empty($this->from) && !empty($this->to) && $this->to < $this->from) {
                $validator->errors()->add('to', 'La fecha "hasta" debe ser mayor o igual a "desde".');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación',
            'errors'  => $validator->errors(),
        ], 400));
    }
}
