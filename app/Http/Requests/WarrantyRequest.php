<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WarrantyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('warranties.issue');
    }

    public function rules(): array
    {
        return ['work_order_id' => ['required', 'integer', 'exists:work_orders,id']];
    }
}
