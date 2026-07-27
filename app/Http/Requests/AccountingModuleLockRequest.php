<?php

namespace App\Http\Requests;

use App\Services\AccountingModuleLockService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountingModuleLockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['prohibited'], 'status' => ['prohibited'],
            'modules' => ['present', 'array'], 'modules.*' => [Rule::in(AccountingModuleLockService::MODULES)],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
