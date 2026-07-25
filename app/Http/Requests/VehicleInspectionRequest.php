<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehicleInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('vehicle_inspections.update') ?? false;
    }

    public function rules(): array
    {
        return ['odometer' => ['nullable', 'integer', 'min:0'], 'fuel_level' => ['nullable', 'numeric', 'between:0,100'], 'approved_by_customer_name' => ['nullable', 'string', 'max:255'], 'general_notes' => ['nullable', 'string'], 'items' => ['required', 'array', 'min:1'], 'items.*.section' => ['required', 'string', 'max:60'], 'items.*.item_code' => ['required', 'string', 'max:80'], 'items.*.item_name' => ['required', 'string', 'max:255'], 'items.*.condition' => ['required', 'string', 'max:30'], 'items.*.severity' => ['nullable', 'string', 'max:20'], 'items.*.is_existing_damage' => ['nullable', 'boolean'], 'items.*.notes' => ['nullable', 'string'], 'items.*.x_position' => ['nullable', 'numeric', 'between:0,100'], 'items.*.y_position' => ['nullable', 'numeric', 'between:0,100']];
    }
}
