<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRelatedRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = match ($this->route()->getName()) {
            'customers.contacts.store' => 'customers.manage_contacts',
            'customers.addresses.store' => 'customers.manage_addresses',
            'customers.notes.store' => 'customers.manage_notes',
            default => '',
        };

        return $permission !== '' && $this->user()->hasPermission($permission)
            && $this->user()->can('view', $this->route('customer'));
    }

    public function rules(): array
    {
        return match ($this->route()->getName()) {
            'customers.contacts.store' => [
                'name' => ['required', 'string', 'max:255'], 'job_title' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email'],
                'is_primary' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            'customers.addresses.store' => [
                'label' => ['required', 'string', 'max:255'],
                'address_type' => ['required', Rule::in(['billing', 'service', 'shipping', 'other'])],
                'country_code' => ['required', 'string', 'size:2'], 'city' => ['nullable', 'string', 'max:255'],
                'district' => ['nullable', 'string', 'max:255'], 'street' => ['nullable', 'string', 'max:255'],
                'building_number' => ['nullable', 'string', 'max:30'], 'postal_code' => ['nullable', 'string', 'max:30'],
                'address_line' => ['nullable', 'string', 'max:2000'], 'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'], 'is_default' => ['nullable', 'boolean'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'customers.notes.store' => [
                'note' => ['required', 'string', 'max:5000'],
                'visibility' => ['required', Rule::in(['company', 'branch', 'private'])],
            ],
            default => [],
        };
    }
}
