<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('vehicle_inspections.delivery');
    }

    public function rules(): array
    {
        return [
            'odometer' => ['nullable', 'integer', 'min:0'],
            'fuel_level' => ['nullable', 'numeric', 'between:0,100'],
            'delivered_items' => ['nullable', 'array'],
            'delivered_items.*' => ['string', 'max:255'],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'receiver_contact' => ['nullable', 'string', 'max:50'],
            'general_notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.section' => ['required', 'string', 'max:60'],
            'items.*.item_code' => ['required', 'string', 'max:80', 'distinct'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.condition' => ['required', 'string', 'max:30'],
            'items.*.severity' => ['nullable', 'string', 'max:20'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
