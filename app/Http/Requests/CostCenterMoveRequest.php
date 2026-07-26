<?php

namespace App\Http\Requests;

class CostCenterMoveRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected(['parent_cost_center_id' => ['nullable', 'integer']]);
    }
}
