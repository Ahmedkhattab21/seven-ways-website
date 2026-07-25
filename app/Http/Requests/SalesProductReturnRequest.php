<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesProductReturnRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->input('idempotency_key', $this->header('Idempotency-Key')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer'], 'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
