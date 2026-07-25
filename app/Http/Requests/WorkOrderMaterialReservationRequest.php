<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkOrderMaterialReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('work_order_materials.reserve') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
