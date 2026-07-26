<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CostCenterRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'branch_id' => ['nullable', 'integer'], 'parent_cost_center_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:50'], 'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'cost_center_type' => ['required', Rule::in([
                'company', 'branch', 'department', 'workshop', 'warehouse', 'sales',
                'marketing', 'project', 'administration', 'other',
            ])],
            'is_header' => ['required', 'boolean'], 'is_posting' => ['required', 'boolean'],
            'manager_employee_id' => ['nullable', 'integer'], 'is_active' => ['boolean'],
        ]);
    }
}
