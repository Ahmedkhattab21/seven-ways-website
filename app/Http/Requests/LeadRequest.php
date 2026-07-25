<?php

namespace App\Http\Requests;

use App\Core\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('lead')
            ? $this->user()->can('update', $this->route('lead'))
            : $this->user()->hasPermission('leads.create');
    }

    public function rules(): array
    {
        $companyId = app(TenantContext::class)->companyId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'vehicle_brand_id' => ['nullable', 'exists:vehicle_brands,id'],
            'vehicle_model_id' => ['nullable', 'exists:vehicle_models,id'],
            'vehicle_year' => ['nullable', 'integer', 'between:1900,2200'],
            'requested_service_text' => ['nullable', 'string', 'max:5000'],
            'source_id' => ['nullable', Rule::exists('customer_sources', 'id')->where('company_id', $companyId)],
            'status' => ['required', Rule::in(['new', 'contacted', 'qualified', 'proposal_requested', 'follow_up', 'won', 'lost', 'cancelled'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'next_follow_up_at' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'required_if:status,lost', 'string', 'max:2000'],
        ];
    }
}
