<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuotationApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['approval_notes' => ['nullable', 'string', 'max:2000'], 'reason' => ['nullable', 'string', 'max:2000']];
    }
}
