<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkOrderScrapConsumptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('work_order_materials.consume_scrap') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
