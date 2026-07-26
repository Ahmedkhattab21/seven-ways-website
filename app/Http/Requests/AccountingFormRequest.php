<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class AccountingFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function protectedRules(): array
    {
        return [
            'company_id' => ['prohibited'], 'status' => ['prohibited'], 'level' => ['prohibited'],
            'path' => ['prohibited'], 'account_level' => ['prohibited'], 'account_path' => ['prohibited'],
            'created_by' => ['prohibited'], 'updated_by' => ['prohibited'], 'approved_by' => ['prohibited'],
            'posted_at' => ['prohibited'], 'total_debit' => ['prohibited'], 'total_credit' => ['prohibited'],
            'document_number' => ['prohibited'],
        ];
    }

    protected function withProtected(array $rules): array
    {
        return $rules + $this->protectedRules();
    }
}
