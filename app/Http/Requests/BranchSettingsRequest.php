<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BranchSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('system_admin') || $this->user()->hasPermission('branch_settings.manage');
    }

    public function rules(): array
    {
        return [
            'default_tax_id' => ['nullable', 'integer'],
            'default_payment_method_id' => ['nullable', 'integer'],
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'quotation_prefix' => ['nullable', 'string', 'max:20'],
            'appointment_prefix' => ['nullable', 'string', 'max:20'],
            'work_order_prefix' => ['nullable', 'string', 'max:20'],
            'purchase_order_prefix' => ['nullable', 'string', 'max:20'],
            'stock_transfer_prefix' => ['nullable', 'string', 'max:20'],
            'warranty_prefix' => ['nullable', 'string', 'max:20'],
            'maximum_discount_percentage' => ['required', 'numeric', 'between:0,100'],
            'requires_discount_approval' => ['nullable', 'boolean'],
            'requires_invoice_cancel_approval' => ['nullable', 'boolean'],
            'allow_negative_stock' => ['nullable', 'boolean'],
            'working_day_start' => ['nullable', 'date_format:H:i'],
            'working_day_end' => ['nullable', 'date_format:H:i', 'after:working_day_start'],
            'weekend_days' => ['nullable', 'array'],
            'weekend_days.*' => ['integer', 'between:0,6'],
        ];
    }
}
