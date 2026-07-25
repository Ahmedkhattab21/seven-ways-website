<?php

namespace App\Http\Requests;

use App\Core\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = match ($this->route()->getName()) {
            'leads.follow-ups.store' => 'leads.follow_up',
            'leads.convert' => 'leads.convert',
            'leads.lost' => 'leads.close',
            default => '',
        };

        return $permission && $this->user()->hasPermission($permission)
            && $this->user()->can('view', $this->route('lead'));
    }

    public function rules(): array
    {
        $companyId = app(TenantContext::class)->companyId();

        return match ($this->route()->getName()) {
            'leads.follow-ups.store' => [
                'follow_up_type' => ['required', Rule::in(['call', 'whatsapp', 'email', 'visit', 'other'])],
                'scheduled_at' => ['nullable', 'date'], 'completed_at' => ['nullable', 'date'],
                'outcome' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:3000'],
                'next_follow_up_at' => ['nullable', 'date'],
                'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
            ],
            'leads.convert' => [
                'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('company_id', $companyId)],
                'create_vehicle' => ['nullable', 'boolean'],
                'confirm_duplicate' => ['nullable', 'boolean'],
            ],
            'leads.lost' => ['lost_reason' => ['required', 'string', 'max:2000']],
            default => [],
        };
    }
}
