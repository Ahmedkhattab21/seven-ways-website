<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BranchResponsibleRemovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(['company_owner', 'general_manager', 'system_admin']);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
