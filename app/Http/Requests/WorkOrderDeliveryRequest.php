<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkOrderDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('work_orders.deliver');
    }

    public function rules(): array
    {
        return [
            'receiver_name' => ['required', 'string', 'max:255'],
            'receiver_contact' => ['nullable', 'string', 'max:50'],
        ];
    }
}
