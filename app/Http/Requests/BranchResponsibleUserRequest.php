<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BranchResponsibleUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('branches.assign_responsible')
            || $this->user()->hasRole('system_admin');
    }

    public function rules(): array
    {
        return [
            'responsible_user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
