<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReworkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $action = (string) $this->route('action');

        return (bool) $this->user()?->hasPermission('rework_orders.'.($action === 'approve' ? 'approve' : $action));
    }

    public function rules(): array
    {
        return ['rework_service_id' => ['nullable', 'integer', 'exists:rework_order_services,id']];
    }
}
