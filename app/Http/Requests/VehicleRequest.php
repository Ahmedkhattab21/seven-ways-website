<?php

namespace App\Http\Requests;

use App\Core\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('vehicle')
            ? $this->user()->can('update', $this->route('vehicle'))
            : $this->user()->hasPermission('vehicles.create');
    }

    public function rules(): array
    {
        $companyId = app(TenantContext::class)->companyId();
        $vehicle = $this->route('vehicle');

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'vehicle_brand_id' => ['required', 'exists:vehicle_brands,id'],
            'vehicle_model_id' => ['required', 'exists:vehicle_models,id'],
            'vehicle_type_id' => ['nullable', 'exists:vehicle_types,id'],
            'vehicle_size_id' => ['nullable', 'exists:vehicle_sizes,id'],
            'manufacturing_year' => ['nullable', 'integer', 'between:1900,2200'],
            'color' => ['nullable', 'string', 'max:50'],
            'plate_number' => ['nullable', 'string', 'max:50'],
            'vin' => [
                'nullable', 'string', 'max:50',
                Rule::unique('vehicles')->where(fn (Builder $query) => $query->where('company_id', $companyId))->ignore($vehicle),
            ],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive', 'sold', 'archived'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
