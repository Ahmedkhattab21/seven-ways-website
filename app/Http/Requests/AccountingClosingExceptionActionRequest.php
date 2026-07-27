<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountingClosingExceptionActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['company_id' => ['prohibited'], 'status' => ['prohibited'], 'reason' => ['required', 'string', 'min:5', 'max:2000']];
    }
}
